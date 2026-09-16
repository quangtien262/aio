<?php

namespace App\Console\Commands;

use App\Support\Database\ResolvesModuleRollbackPaths;
use Illuminate\Database\Console\Migrations\ResetCommand;

class ModuleAwareResetCommand extends ResetCommand
{
    use ResolvesModuleRollbackPaths;
}
