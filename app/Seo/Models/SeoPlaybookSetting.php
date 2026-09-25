<?php

namespace App\Seo\Models;

use Illuminate\Database\Eloquent\Model;

class SeoPlaybookSetting extends Model
{
    protected $table = 'seo_playbook_settings';

    protected $fillable = ['key', 'value'];
}
