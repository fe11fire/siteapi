<?php

namespace SiteApi\Root\Providers\Logger;

use SiteApi\Root\Providers\Logger\LoggerContract;

enum LoggerEnum: string
{
    case FILE = 'file';
    case STDOUT = 'stdout';

    /**
     * Создаёт новый провайдер выбранного вида без записи события.
     */
    public function getProvider(): LoggerContract
    {
        return match ($this) {
            LoggerEnum::FILE => new FileLogger(),
            LoggerEnum::STDOUT => new StdoutLogger(),
        };
    }
}
