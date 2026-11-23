<?php

namespace Tests;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! User::where('email', 'demo@riwaq.test')->exists() || ! Book::exists()) {
            $this->seed();
        }
    }
}
