<?php

namespace App\Contracts\Search;

interface SearchEngine extends SearchService
{
    public function available(): bool;

    public function name(): string;
}
