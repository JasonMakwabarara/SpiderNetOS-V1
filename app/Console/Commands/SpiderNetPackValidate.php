<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SpiderNetPackValidate extends Command
{
    protected $signature = 'spidernet:pack-validate {path}';
    protected $description = 'Validate a feature pack manifest';

    public function handle()
    {
        $path = $this->argument('path');
        
        if (!File::exists($path)) {
            $this->error('File not found: ' . $path);
            return 1;
        }
        
        $manifest = json_decode(File::get($path), true);
        
        if (!$manifest) {
            $this->error('Invalid JSON in manifest');
            return 1;
        }
        
        if (!isset($manifest['name'])) {
            $this->error('Missing "name" field');
            return 1;
        }
        
        if (!isset($manifest['version'])) {
            $this->error('Missing "version" field');
            return 1;
        }
        
        if (!isset($manifest['publisher'])) {
            $this->error('Missing "publisher" field');
            return 1;
        }
        
        $this->info('✅ Feature pack is valid!');
        $this->line("Publisher: {$manifest['publisher']}");
        $this->line("Name: {$manifest['name']}");
        $this->line("Version: {$manifest['version']}");
        
        return 0;
    }
}