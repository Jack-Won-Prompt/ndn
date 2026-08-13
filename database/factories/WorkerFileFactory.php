<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerFile>
 */
class WorkerFileFactory extends Factory
{
    protected $model = WorkerFile::class;

    public function definition(): array
    {
        return [
            'worker_id' => Worker::factory(),
            'type' => WorkerFileType::Passport->value,
            'path' => WorkerFile::DIR.'/1/passport_test.pdf',
            'original_name' => '여권사본.pdf',
            'size' => 102400,
            'mime' => 'application/pdf',
            'expires_on' => null,
            'note' => null,
            'uploaded_by' => null,
        ];
    }
}
