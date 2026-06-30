<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Bayut Demo API",
    description: "API Documentation"
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "Local Server"
)]
class OpenApi
{
}