<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Enums;

/**
 * 본사가 근로자 한 사람에 대해 보관하는 서류의 종류.
 *
 * 전원 공통 서식(required_documents)과 다르다. 그쪽은 '모두가 동의해야 하는
 * 약관·계약서' 이고, 이쪽은 '이 사람의 여권 사본·건강검진 결과' 처럼 사람마다
 * 다른 개인 서류다.
 */
enum WorkerFileType: string
{
    case Passport = 'passport';
    case Visa = 'visa';
    case Health = 'health';
    case Contract = 'contract';
    case Criminal = 'criminal';
    case Photo = 'photo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Passport => '여권 사본',
            self::Visa => '비자·체류 서류',
            self::Health => '건강검진 결과',
            self::Contract => '근로계약서',
            self::Criminal => '범죄경력 증명',
            self::Photo => '증명사진',
            self::Other => '기타',
        };
    }

    /** @return array<string, string> 화면 선택지 */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
