<?php

namespace App\Http\Controllers;

#[\OpenApi\Attributes\Info(
    version: '1.0.0',
    title: 'API Documentation',
    description: 'API Documentation',
    license: new \OpenApi\Attributes\License(name: 'Apache 2.0', url: 'http://www.apache.org/licenses/LICENSE-2.0.html'),
)]
#[\OpenApi\Attributes\Server(url: L5_SWAGGER_CONST_HOST, description: 'Wilmering API Server')]
abstract class Controller {}
