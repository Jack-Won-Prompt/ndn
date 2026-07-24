<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    // Laravel 12 는 기본 Controller 에 이 trait 들을 넣지 않는다.
    // 컨트롤러에서 $this->authorize() 를 쓰려면 명시적으로 포함해야 한다.
    use AuthorizesRequests, ValidatesRequests;
}
