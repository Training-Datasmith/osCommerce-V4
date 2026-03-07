<?php

declare(strict_types=1);

namespace common\modules\email\Mandrill;

use common\modules\email\Mandrill;

class Internal
{
    public function __construct(Mandrill $master)
    {
        $this->master = $master;
    }

}
