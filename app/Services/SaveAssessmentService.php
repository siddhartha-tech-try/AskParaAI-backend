<?php

namespace App\Services;

use App\Models\Assessment;
use Illuminate\Support\Facades\DB;

class SaveAssessmentService
{
    public function execute(array $data): Assessment
    {
        return DB::transaction(function () use ($data) {

            $assessment = Assessment::create([
                'title'       => $data['title'] ?? null,
                'context'     => $data['context'],
                'model_used'  => $data['model_used'],
                'source_type' => $data['source_type'] ?? 'text',
            ]);

            foreach ($data['questions'] as $question) {
                $assessment->questions()->create([
                    'question_text'  => $question['question'],
                    'question_type'  => $question['type'],
                    'options'        => $question['options'] ?? null,
                    'correct_answer' => $question['correct_answer'] ?? null,
                ]);
            }

            return $assessment;
        });
    }
}
