<?php

declare(strict_types=1);

namespace WPCBTPro\Questions\Contracts;

enum QuestionCategory: string
{
    case Objective = 'objective';
    case Written = 'written';
    case Stem = 'stem';
    case Programming = 'programming';
    case Dsa = 'dsa';

    public function label(): string
    {
        return match ($this) {
            self::Objective => __('Objective', 'wp-cbt-pro'),
            self::Written => __('Written', 'wp-cbt-pro'),
            self::Stem => __('STEM', 'wp-cbt-pro'),
            self::Programming => __('Programming', 'wp-cbt-pro'),
            self::Dsa => __('Data Structures & Algorithms', 'wp-cbt-pro'),
        };
    }
}
