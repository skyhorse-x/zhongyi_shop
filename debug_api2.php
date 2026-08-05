<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Get the latest 5 reports with tasks
$reports = \App\Models\AnalysisReport::with('task')
    ->orderBy('id', 'desc')
    ->limit(5)
    ->get();

echo "=== Latest 5 Reports ===\n\n";

foreach ($reports as $report) {
    echo "Report #{$report->id}:\n";
    echo "- User: {$report->user->username}\n";
    echo "- Type: {$report->type}\n";
    echo "- Task ID: {$report->task_id}\n";
    
    if ($report->task) {
        echo "- Task No: {$report->task->task_no}\n";
        echo "- Image URL: " . ($report->task->image_url ?: 'NULL') . "\n";
        echo "- Image URLs: " . json_encode($report->task->image_urls) . "\n";
        echo "- Raw Image URLs: " . $report->task->getRawOriginal('image_urls') . "\n";
    } else {
        echo "- Task: NOT LOADED\n";
    }
    echo "\n";
}
