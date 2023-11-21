<?php

declare(strict_types=1);

return new DatePeriod(
    start: new DateTime(),
    interval: new DateInterval('PT2S'),
    end: new DateTime('+5 minutes'),
);
