<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$taskNo = 'TK20260805d3e5afd7';

$task = \App\Models\AnalysisTask::where('task_no', $taskNo)->first();

if ($task) {
    echo "Task found:\n";
    echo "ID: {$task->id}\n";
    echo "Task No: {$task->task_no}\n";
    echo "Type: {$task->type}\n";
    echo "Image URL: {$task->image_url}\n";
    echo "Image URLs: " . json_encode($task->image_urls) . "\n";
    echo "Raw Image URLs (database): " . $task->getRawOriginal('image_urls') . "\n";
    echo "Status: {$task->status}\n";
} else {
    echo "Task not found\n";
}

// Check the latest task
echo "\n=== Latest Task ===\n";
$latestTask = \App\Models\AnalysisTask::orderBy('id', 'desc')->first();
if ($latestTask) {
    echo "ID: {$latestTask->id}\n";
    echo "Task No: {$latestTask->task_no}\n";
    echo "Image URL: {$latestTask->image_url}\n";
    echo "Image URLs: " . json_encode($latestTask->image_urls) . "\n";
}
