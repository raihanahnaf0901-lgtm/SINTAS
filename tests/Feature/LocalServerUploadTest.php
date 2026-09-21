<?php

namespace Tests\Feature;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LocalServerUploadTest extends TestCase
{
    #[TestWith([1048576])]
    #[TestWith([10485760])]
    public function test_local_server_receives_multipart_files_using_the_users_temporary_directory(int $size): void
    {
        $disk = Storage::fake('local');
        $disk->makeDirectory('php-upload-temp');
        $temporaryDirectory = $disk->path('php-upload-temp');
        $originalEnvironment = $_ENV;
        foreach (['TEMP', 'TMP', 'TMPDIR'] as $variable) {
            $_ENV[$variable] = $temporaryDirectory;
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $command = new class extends ServeCommand
        {
            private string $address;

            public function startProbe(string $address): Process
            {
                $this->address = $address;
                $this->input = new ArrayInput([], $this->getDefinition());
                $this->phpServerWorkers = false;

                return $this->startProcess(true);
            }

            protected function serverCommand(): array
            {
                return [PHP_BINARY, '-d', 'upload_tmp_dir=', '-d', 'sys_temp_dir=',
                    '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M',
                    '-S', $this->address, base_path('tests/Fixtures/upload-probe.php')];
            }

            protected function handleProcessOutput(): callable
            {
                return static function (string $type, string $buffer): void {};
            }
        };
        $command->setLaravel($this->app);
        $command->setApplication(new Application);
        $process = null;

        try {
            $process = $command->startProbe($address);
            $process->setTimeout(15);
            $this->assertTrue($process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'Development Server')));
            $content = str_repeat('a', $size);
            $boundary = 'sintas-upload-test-boundary';
            $body = '--'.$boundary."\r\nContent-Disposition: form-data; name=\"file\"; filename=\"test.txt\"\r\nContent-Type: text/plain\r\n\r\n".$content."\r\n--".$boundary."--\r\n";
            $context = stream_context_create(['http' => [
                'method' => 'POST', 'timeout' => 10,
                'header' => 'Content-Type: multipart/form-data; boundary='.$boundary,
                'content' => $body,
            ]]);

            $response = file_get_contents('http://'.$address, false, $context);

            $this->assertJson($response);
            $data = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(UPLOAD_ERR_OK, $data['error']);
            $this->assertSame($size, $data['size']);
            $this->assertSame(hash('sha256', $content), $data['sha256']);
            $this->assertSame(realpath($temporaryDirectory), $data['temporary_directory']);
        } finally {
            $process?->stop(1);
            $command->untrap();
            $_ENV = $originalEnvironment;
        }
    }
}
