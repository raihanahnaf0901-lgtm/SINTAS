<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\KeanggotaanSekolah;
use App\Models\KelasMapel;
use App\Models\PembayaranSekolah;
use App\Models\Sekolah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SchoolPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const CHECKOUT = 'https://app.sandbox.midtrans.com/snap/v1/transactions';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'school-subscriptions.sandbox' => true,
            'school-subscriptions.server_key' => 'sandbox-test-key',
            'school-subscriptions.plans' => [
                'test-plan' => ['name' => 'Paket pengujian', 'amount' => 100000, 'duration_days' => 30],
                'bulanan' => ['name' => 'Langganan sekolah bulanan', 'amount' => 75000, 'duration_months' => 1, 'duration_days' => null],
            ],
        ]);
    }

    public function test_checkout_uses_server_prices_and_reuses_the_existing_pending_order(): void
    {
        $school = $this->pendingSchool();
        Http::preventStrayRequests();
        Http::fake([self::CHECKOUT => Http::response([
            'token' => 'test-token', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/test-token',
        ], 201)]);

        $first = $this->actingAs($school->admin->user)->postJson("/api/v1/sekolah/{$school->id}/pembayaran", [
            'plan_code' => 'test-plan', 'amount' => 1, 'duration_days' => 999,
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        Http::assertSentCount(1);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
            && $request['transaction_details']['gross_amount'] === 100000
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sandbox-test-key:')));
        $this->assertDatabaseCount('pembayaran_sekolah', 1);
        $this->assertDatabaseHas('pembayaran_sekolah', ['amount' => 100000, 'duration_days' => 30]);
        $this->assertSame('pending', $school->fresh()->status);
        $this->assertFalse($school->admin->user->fresh()->hasRole('admin_sekolah'));
    }

    public function test_checkout_requires_authentication_and_school_owner_authorization(): void
    {
        $school = $this->pendingSchool();
        $otherTeacher = Guru::factory()->create();
        Http::preventStrayRequests();

        $this->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])->assertUnauthorized();
        $this->actingAs($otherTeacher->user)
            ->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])->assertForbidden();

        $this->assertDatabaseCount('pembayaran_sekolah', 0);
        Http::assertNothingSent();
    }

    #[TestWith(['plans', [], 'plan_code'])]
    #[TestWith(['server_key', '', 'payment'])]
    public function test_unconfigured_checkout_does_not_create_payment_or_call_gateway(string $key, mixed $value, string $field): void
    {
        config(['school-subscriptions.'.$key => $value]);
        $school = $this->pendingSchool();
        Http::preventStrayRequests();

        $this->actingAs($school->admin->user)->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('pembayaran_sekolah', 0);
        Http::assertNothingSent();
    }

    public function test_settled_webhook_activates_owner_and_school_only_once(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 12:00:00'));
        $school = $this->pendingSchool();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();
        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertDatabaseHas('pembayaran_sekolah', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('keanggotaan_sekolah', ['guru_id' => $school->admin_guru_id, 'status' => 'diterima']);
        $this->assertSame('aktif', $school->fresh()->status);
        $this->assertSame('2026-10-28 12:00:00', $school->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertTrue($school->admin->user->fresh()->hasRole('admin_sekolah'));
        $this->assertSame('guru', $school->admin->user->fresh()->role);
        Http::assertSentCount(2);
    }

    public function test_payment_activation_adopts_only_the_owners_unassigned_legacy_classes(): void
    {
        $school = $this->pendingSchool();
        $legacy = KelasMapel::factory()->create(['guru_pembuat_id' => $school->admin_guru_id]);
        $otherSchool = Sekolah::factory()->create();
        $assignedElsewhere = KelasMapel::factory()->create([
            'guru_pembuat_id' => $school->admin_guru_id, 'sekolah_id' => $otherSchool->id,
        ]);
        $otherTeachersClass = KelasMapel::factory()->create();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertDatabaseHas('kelas_mapel', ['id' => $legacy->id, 'sekolah_id' => $school->id]);
        $this->assertDatabaseHas('kelas_mapel', ['id' => $assignedElsewhere->id, 'sekolah_id' => $otherSchool->id]);
        $this->assertDatabaseHas('kelas_mapel', ['id' => $otherTeachersClass->id, 'sekolah_id' => null]);
        Http::assertSentCount(1);
    }

    public function test_forged_signature_is_rejected_without_gateway_request(): void
    {
        $payment = PembayaranSekolah::factory()->create();
        Http::preventStrayRequests();

        $this->postJson('/api/payments/midtrans/notification', [
            ...$this->notification($payment), 'signature_key' => str_repeat('0', 128),
        ])->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_webhook_status_is_not_trusted_when_server_status_is_pending(): void
    {
        $school = $this->pendingSchool();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment, [
            'transaction_status' => 'pending', 'status_code' => '201',
        ]))]);

        $this->postJson('/api/payments/midtrans/notification', [
            ...$this->notification($payment), 'transaction_status' => 'settlement',
        ])->assertOk();

        $this->assertSame('pending', $school->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertSentCount(1);
    }

    #[TestWith(['gross_amount', '1.00'])]
    #[TestWith(['currency', 'USD'])]
    #[TestWith(['order_id', 'another-order'])]
    public function test_verified_response_must_match_local_invoice(string $field, string $value): void
    {
        $school = $this->pendingSchool();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment, [$field => $value]))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertUnprocessable();

        $this->assertSame('pending', $school->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertSentCount(1);
    }

    #[TestWith(['capture', 'challenge', '200'])]
    #[TestWith(['capture', null, '200'])]
    #[TestWith(['settlement', 'deny', '200'])]
    #[TestWith(['settlement', 'accept', '201'])]
    #[TestWith(['refund', 'accept', '200'])]
    public function test_unconfirmed_or_refunded_payment_does_not_activate_school(string $status, ?string $fraud, string $code): void
    {
        $school = $this->pendingSchool();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment, [
            'transaction_status' => $status, 'fraud_status' => $fraud, 'status_code' => $code,
        ]))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertSame('pending', $school->fresh()->status);
        $this->assertNull($payment->fresh()->activated_at);
        $this->assertFalse($school->admin->user->fresh()->hasRole('admin_sekolah'));
        Http::assertSentCount(1);
    }

    public function test_owner_can_synchronize_and_renew_from_existing_end_date_without_webhook(): void
    {
        $this->travelTo(Carbon::parse('2026-09-28 12:00:00'));
        $school = Sekolah::factory()->withAdmin()->create(['subscription_ends_at' => '2026-10-10 12:00:00']);
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::sequence()
            ->push($this->verifiedStatus($payment, ['transaction_status' => 'capture']))
            ->push($this->verifiedStatus($payment, ['transaction_status' => 'pending', 'status_code' => '201']))]);

        $this->actingAs($school->admin->user)->postJson("/api/v1/pembayaran-sekolah/{$payment->id}/sinkronisasi")
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/v1/pembayaran-sekolah/{$payment->id}/sinkronisasi")
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertSame('2026-11-09 12:00:00', $school->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        Http::assertSentCount(2);
    }

    public function test_a_different_school_cannot_synchronize_payment(): void
    {
        $payment = PembayaranSekolah::factory()->create();
        Http::preventStrayRequests();

        $this->actingAs(Guru::factory()->create()->user)
            ->postJson("/api/v1/pembayaran-sekolah/{$payment->id}/sinkronisasi")->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_ambiguous_checkout_timeout_does_not_create_a_second_order(): void
    {
        $school = $this->pendingSchool();
        Http::preventStrayRequests();
        Http::fake([self::CHECKOUT => Http::failedConnection()]);

        $this->actingAs($school->admin->user)->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');

        $this->assertDatabaseCount('pembayaran_sekolah', 1);
        $this->assertDatabaseHas('pembayaran_sekolah', ['status' => 'creating']);
        Http::assertSentCount(1);
    }

    public function test_known_gateway_validation_failure_allows_explicit_retry(): void
    {
        $school = $this->pendingSchool();
        Http::preventStrayRequests();
        Http::fake([self::CHECKOUT => Http::sequence()
            ->push(['error_messages' => ['private-provider-error']], 400)
            ->push(['redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/retry'], 201)]);

        $this->actingAs($school->admin->user)->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertUnprocessable()->assertDontSee('private-provider-error');
        $this->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseCount('pembayaran_sekolah', 2);
        $this->assertDatabaseHas('pembayaran_sekolah', ['status' => 'failed']);
        Http::assertSentCount(2);
    }

    public function test_status_404_keeps_ambiguous_attempt_without_assuming_payment_failed(): void
    {
        $school = $this->pendingSchool();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create(['status' => 'creating', 'checkout_url' => null]);
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response(['status_code' => '404'], 404)]);

        $this->actingAs($school->admin->user)->postJson("/api/v1/pembayaran-sekolah/{$payment->id}/sinkronisasi")
            ->assertOk()->assertJsonPath('data.status', 'creating');

        $this->assertSame('pending', $school->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_provider_outage_returns_retryable_webhook_error_without_activation(): void
    {
        $payment = PembayaranSekolah::factory()->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response([], 500)]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertServiceUnavailable();

        $this->assertSame('pending', $payment->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_paid_invoice_does_not_reactivate_manually_disabled_school(): void
    {
        $school = $this->pendingSchool();
        $school->update(['status' => 'nonaktif']);
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create();
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertSame('nonaktif', $school->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->activated_at);
        $this->assertFalse($school->admin->user->fresh()->hasRole('admin_sekolah'));
        Http::assertSentCount(1);
    }

    public function test_checkout_rejects_redirects_outside_midtrans(): void
    {
        $school = $this->pendingSchool();
        Http::preventStrayRequests();
        Http::fake([self::CHECKOUT => Http::response(['redirect_url' => 'https://evil.example/checkout'], 201)]);

        $this->actingAs($school->admin->user)->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'test-plan'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment')->assertDontSee('evil.example');

        $this->assertDatabaseHas('pembayaran_sekolah', ['status' => 'creating', 'checkout_url' => null]);
        Http::assertSentCount(1);
    }

    public function test_monthly_checkout_is_separate_for_each_school_of_the_same_founder(): void
    {
        $owner = Guru::factory()->create();
        $firstSchool = $this->pendingSchool($owner);
        $secondSchool = $this->pendingSchool($owner);
        Http::preventStrayRequests();
        Http::fake([self::CHECKOUT => fn (ClientRequest $request) => Http::response([
            'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtweb/'.$request['transaction_details']['order_id'],
        ], 201)]);

        $first = $this->actingAs($owner->user)->postJson("/api/v1/sekolah/{$firstSchool->id}/pembayaran", [
            'plan_code' => 'bulanan', 'amount' => 1, 'duration_days' => 999, 'duration_months' => 99,
        ])->assertCreated()->assertJsonPath('data.amount', 75000)
            ->assertJsonPath('data.duration_months', 1)->assertJsonPath('data.duration_days', null);
        $second = $this->postJson("/api/v1/sekolah/{$secondSchool->id}/pembayaran", ['plan_code' => 'bulanan'])
            ->assertCreated()->assertJsonPath('data.amount', 75000)
            ->assertJsonPath('data.duration_months', 1)->assertJsonPath('data.duration_days', null);
        $this->postJson("/api/v1/sekolah/{$firstSchool->id}/pembayaran", ['plan_code' => 'bulanan'])
            ->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertNotSame($first->json('data.order_id'), $second->json('data.order_id'));
        $this->assertDatabaseCount('pembayaran_sekolah', 2);
        $this->assertDatabaseHas('pembayaran_sekolah', [
            'id' => $first->json('data.id'), 'sekolah_id' => $firstSchool->id, 'amount' => 75000,
            'duration_months' => 1, 'duration_days' => null,
        ]);
        $this->assertDatabaseHas('pembayaran_sekolah', [
            'id' => $second->json('data.id'), 'sekolah_id' => $secondSchool->id, 'amount' => 75000,
            'duration_months' => 1, 'duration_days' => null,
        ]);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (ClientRequest $request): bool => $request['transaction_details']['gross_amount'] !== 75000);
    }

    #[TestWith(['2027-01-31 12:00:00', '2027-02-28 12:00:00'])]
    #[TestWith(['2028-01-31 12:00:00', '2028-02-29 12:00:00'])]
    public function test_monthly_activation_clamps_to_the_last_day_of_february(string $start, string $expectedEnd): void
    {
        $this->travelTo(Carbon::parse($start));
        $school = $this->pendingSchool();
        $payment = $this->monthlyPayment($school);
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertSame($expectedEnd, $school->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('paid', $payment->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_a_legacy_day_invoice_keeps_its_original_duration_after_monthly_plans_are_added(): void
    {
        $this->travelTo(Carbon::parse('2027-01-31 12:00:00'));
        $school = $this->pendingSchool();
        $payment = PembayaranSekolah::factory()->for($school, 'sekolah')->create([
            'duration_days' => 30, 'duration_months' => null,
        ]);
        config(['school-subscriptions.plans.test-plan' => [
            'name' => 'Paket diperbarui', 'amount' => 75000, 'duration_months' => 1,
        ]]);
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertSame('2027-03-02 12:00:00', $school->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('pembayaran_sekolah', [
            'id' => $payment->id, 'amount' => 100000, 'duration_days' => 30, 'duration_months' => null,
        ]);
        Http::assertSentCount(1);
    }

    public function test_payment_for_one_school_does_not_activate_another_school_with_the_same_founder(): void
    {
        $this->travelTo(Carbon::parse('2027-01-31 12:00:00'));
        $owner = Guru::factory()->create();
        $firstSchool = $this->pendingSchool($owner);
        $secondSchool = $this->pendingSchool($owner);
        $firstPayment = $this->monthlyPayment($firstSchool);
        $secondPayment = $this->monthlyPayment($secondSchool);
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($firstPayment) => Http::response($this->verifiedStatus($firstPayment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($firstPayment))->assertOk();
        $this->postJson('/api/payments/midtrans/notification', $this->notification($firstPayment))->assertOk();

        $this->assertSame('2027-02-28 12:00:00', $firstSchool->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('pending', $secondSchool->fresh()->status);
        $this->assertNull($secondSchool->fresh()->subscription_ends_at);
        $this->assertSame('pending', $secondPayment->fresh()->status);
        $this->assertDatabaseHas('keanggotaan_sekolah', [
            'guru_id' => $owner->id, 'sekolah_id' => $firstSchool->id, 'status' => 'diterima',
        ]);
        $this->assertDatabaseHas('keanggotaan_sekolah', [
            'guru_id' => $owner->id, 'sekolah_id' => $secondSchool->id, 'status' => 'pending',
        ]);
        Http::assertSentCount(2);
    }

    public function test_monthly_renewal_extends_only_its_school_once_from_the_existing_end_date(): void
    {
        $this->travelTo(Carbon::parse('2027-01-20 12:00:00'));
        $owner = Guru::factory()->create();
        $school = Sekolah::factory()->withAdmin()->create([
            'admin_guru_id' => $owner->id, 'subscription_ends_at' => '2027-01-31 12:00:00',
        ]);
        $otherSchool = Sekolah::factory()->withAdmin()->create([
            'admin_guru_id' => $owner->id, 'subscription_ends_at' => '2027-03-15 12:00:00',
        ]);
        $payment = $this->monthlyPayment($school);
        config(['school-subscriptions.plans.bulanan.duration_months' => 12]);
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();
        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertSame('2027-02-28 12:00:00', $school->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-03-15 12:00:00', $otherSchool->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('pembayaran_sekolah', [
            'id' => $payment->id, 'status' => 'paid', 'duration_months' => 1, 'duration_days' => null,
        ]);
        Http::assertSentCount(2);
    }

    public function test_expired_monthly_subscription_renews_from_payment_confirmation_time(): void
    {
        $this->travelTo(Carbon::parse('2027-01-31 12:00:00'));
        $school = Sekolah::factory()->withAdmin()->create(['subscription_ends_at' => '2026-12-31 12:00:00']);
        $payment = $this->monthlyPayment($school);
        Http::preventStrayRequests();
        Http::fake([$this->statusUrl($payment) => Http::response($this->verifiedStatus($payment))]);

        $this->postJson('/api/payments/midtrans/notification', $this->notification($payment))->assertOk();

        $this->assertSame('2027-02-28 12:00:00', $school->fresh()->subscription_ends_at->format('Y-m-d H:i:s'));
        Http::assertSentCount(1);
    }

    #[TestWith([30, 1])]
    #[TestWith([null, null])]
    #[TestWith([null, 0])]
    #[TestWith([null, '1'])]
    public function test_checkout_rejects_ambiguous_or_invalid_duration_configuration(?int $days, mixed $months): void
    {
        config(['school-subscriptions.plans.bulanan.duration_days' => $days,
            'school-subscriptions.plans.bulanan.duration_months' => $months]);
        $school = $this->pendingSchool();
        Http::preventStrayRequests();

        $this->actingAs($school->admin->user)->postJson("/api/v1/sekolah/{$school->id}/pembayaran", ['plan_code' => 'bulanan'])
            ->assertUnprocessable()->assertJsonValidationErrors('plan_code');

        $this->assertDatabaseCount('pembayaran_sekolah', 0);
        Http::assertNothingSent();
    }

    private function pendingSchool(?Guru $owner = null): Sekolah
    {
        $school = Sekolah::factory()->pending()->create($owner === null ? [] : ['admin_guru_id' => $owner->id]);
        KeanggotaanSekolah::factory()->create(['sekolah_id' => $school->id, 'guru_id' => $school->admin_guru_id]);

        return $school;
    }

    private function monthlyPayment(Sekolah $school): PembayaranSekolah
    {
        return PembayaranSekolah::factory()->for($school, 'sekolah')->create([
            'plan_code' => 'bulanan', 'plan_name' => 'Langganan sekolah bulanan',
            'amount' => 75000, 'duration_months' => 1, 'duration_days' => null,
        ]);
    }

    private function statusUrl(PembayaranSekolah $payment): string
    {
        return 'https://api.sandbox.midtrans.com/v2/'.$payment->order_id.'/status';
    }

    /** @return array<string, string> */
    private function notification(PembayaranSekolah $payment): array
    {
        $amount = $payment->amount.'.00';

        return [
            'order_id' => $payment->order_id, 'status_code' => '200', 'gross_amount' => $amount,
            'signature_key' => hash('sha512', $payment->order_id.'200'.$amount.'sandbox-test-key'),
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function verifiedStatus(PembayaranSekolah $payment, array $overrides = []): array
    {
        return array_replace([
            'order_id' => $payment->order_id, 'status_code' => '200', 'gross_amount' => $payment->amount.'.00',
            'currency' => 'IDR', 'transaction_status' => 'settlement', 'fraud_status' => 'accept',
            'transaction_id' => 'gateway-'.$payment->id,
        ], $overrides);
    }
}
