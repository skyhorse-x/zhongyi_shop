<?php

namespace App\Console\Commands;

use App\Models\ConstitutionQuestion;
use App\Models\ProductPackage;
use Illuminate\Console\Command;

class CleanDuplicateData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:clean-duplicates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean duplicate data from constitution questions and product packages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting duplicate data cleanup...');

        // 清理重复的体质题目
        $this->cleanDuplicateQuestions();

        // 清理重复的次数包
        $this->cleanDuplicatePackages();

        $this->info('Duplicate data cleanup completed!');
        return Command::SUCCESS;
    }

    /**
     * 清理重复的体质题目
     */
    private function cleanDuplicateQuestions(): void
    {
        $this->info('Cleaning duplicate constitution questions...');

        // 获取所有题目，按 question 分组
        $questions = ConstitutionQuestion::all();
        $seen = [];
        $duplicates = [];

        foreach ($questions as $question) {
            $key = md5($question->question . $question->category);
            if (in_array($key, $seen)) {
                $duplicates[] = $question->id;
            } else {
                $seen[] = $key;
            }
        }

        if (!empty($duplicates)) {
            ConstitutionQuestion::whereIn('id', $duplicates)->delete();
            $this->info("Deleted " . count($duplicates) . " duplicate questions.");
        } else {
            $this->info("No duplicate questions found.");
        }

        $this->info("Remaining questions: " . ConstitutionQuestion::count());
    }

    /**
     * 清理重复的次数包
     */
    private function cleanDuplicatePackages(): void
    {
        $this->info('Cleaning duplicate product packages...');

        // 获取所有次数包，按 name 分组
        $packages = ProductPackage::all();
        $seen = [];
        $duplicates = [];

        foreach ($packages as $package) {
            $key = md5($package->name . $package->type);
            if (in_array($key, $seen)) {
                $duplicates[] = $package->id;
            } else {
                $seen[] = $key;
            }
        }

        if (!empty($duplicates)) {
            ProductPackage::whereIn('id', $duplicates)->delete();
            $this->info("Deleted " . count($duplicates) . " duplicate packages.");
        } else {
            $this->info("No duplicate packages found.");
        }

        $this->info("Remaining packages: " . ProductPackage::count());
    }
}
