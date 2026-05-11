<?php

namespace App\Filament\Resources\Routes\Pages;

use App\Enums\RouteStatus;
use App\Filament\Resources\Routes\RouteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRoute extends CreateRecord
{
    protected static string $resource = RouteResource::class;

//    protected function mutateFormDataBeforeCreate(array $data): array
//    {
//        $data['status'] = RouteStatus::PLANNED;
//
//        return $data;
//    }
}
