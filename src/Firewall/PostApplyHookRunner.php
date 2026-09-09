<?php

declare(strict_types=1);

namespace App\Firewall;

use App\System\Command\FirewallPostHookCommand;
use App\System\CommandExecutor;

final class PostApplyHookRunner
{
    private const HOOK_DIRECTORY = '/etc/cloudpanel/hooks/firewall-post.d';

    public function run(): void
    {
        if (!is_dir(self::HOOK_DIRECTORY)) {
            return;
        }

        $hooks = glob(self::HOOK_DIRECTORY . '/*');

        if (false === $hooks || [] === $hooks) {
            return;
        }

        sort($hooks, SORT_STRING);

        $commandExecutor = new CommandExecutor();

        foreach ($hooks as $hook) {
            if (!is_file($hook) || !is_executable($hook)) {
                continue;
            }

            $command = new FirewallPostHookCommand();
            $command->setHookFile($hook);

            try {
                $commandExecutor->execute($command);
            } catch (\Throwable $e) {
                // Upstream implementation should log this using CloudPanel's logger.
                // A failed post-hook must not undo or misreport a successful firewall apply.
            }
        }
    }
}
