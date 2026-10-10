<?php

namespace Aporat\ServerInfo\Tests\Fixtures;

function sideEffect(): StaticModule
{
    $GLOBALS['server_info_side_effect'] = true;

    return new StaticModule;
}
