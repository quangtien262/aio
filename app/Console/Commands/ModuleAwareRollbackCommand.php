<?php

namespace App\Console\Commands;

use App\Support\Database\ResolvesModuleRollbackPaths;
use Illuminate\Database\Console\Migrations\RollbackCommand;

class ModuleAwareRollbackCommand extends RollbackCommand
{
    use ResolvesModuleRollbackPaths;
}
