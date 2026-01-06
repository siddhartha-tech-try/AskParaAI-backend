<?php

namespace App\Services\Questions;

class QuestionTypePromptFactory
{
    public static function build(array $types): string
    {
        $map = [
            'mcq_single' => 'MCQ (single correct answer)',
            'mcq_multiple' => 'MCQ (multiple correct answers)',
            'fill_blanks' => 'Fill in the blanks',
            'one_word' => 'One word answer',
            'one_liner' => 'One line answer',
            'descriptive' => 'Descriptive long answer',
        ];

        $lines = array_map(
            fn ($t) => "- {$map[$t]}",
            array_intersect($types, array_keys($map))
        );

        return implode("\n", $lines);
    }
}
