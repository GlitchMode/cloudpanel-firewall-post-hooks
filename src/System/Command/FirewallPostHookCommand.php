<?php

declare(strict_types=1);

namespace App\System\Command;

use App\System\Command;

final class FirewallPostHookCommand extends Command
{
    private ?string $hookFile = null;

    public function getCommand(): string
    {
        if ($this->command) {
            return $this->command;
        }

        $hookFile = $this->getHookFile();

        if (null === $hookFile) {
            throw new \InvalidArgumentException('Firewall post-hook file is not set.');
        }

        $this->command = sprintf(
            '/usr/bin/sudo %s',
            escapeshellarg($hookFile)
        );

        return $this->command;
    }

    public function setHookFile(string $hookFile): void
    {
        $this->hookFile = $hookFile;
    }

    public function getHookFile(): ?string
    {
        return $this->hookFile;
    }

    public function isSuccessful(): bool
    {
        return true;
    }
}
