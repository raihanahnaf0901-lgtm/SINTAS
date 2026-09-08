<?php

namespace App;

enum StatusKeanggotaanRuangMapel: string
{
    case Menunggu = 'menunggu';
    case Diterima = 'diterima';
    case Ditolak = 'ditolak';
}
