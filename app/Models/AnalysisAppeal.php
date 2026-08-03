<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalysisAppeal extends Model
{
    protected $fillable = [
        'user_id', 'analysis_id', 'task_no', 'reason', 'description',
        'attachments', 'status', 'audit_note', 'audited_by', 'audited_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'audited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function analysis()
    {
        return $this->belongsTo(Analysis::class);
    }

    public function auditor()
    {
        return $this->belongsTo(Admin::class, 'audited_by');
    }
}
