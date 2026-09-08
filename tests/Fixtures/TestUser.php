<?php

declare(strict_types=1);

namespace EventSolutions\NeventoSocialite\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
