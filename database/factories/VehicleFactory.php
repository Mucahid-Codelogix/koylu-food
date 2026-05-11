<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cars = [
            'Audi' => ['A3', 'A4', 'A6', 'Q3', 'Q5', 'Q7', 'TT', 'e-tron'],
            'BMW' => ['1 Serie', '3 Serie', '5 Serie', 'X1', 'X3', 'X5', 'M3', 'M5'],
            'Mercedes' => ['A-Klasse', 'C-Klasse', 'E-Klasse', 'GLA', 'GLC', 'GLE', 'AMG GT'],
            'Volkswagen' => ['Golf', 'Polo', 'Passat', 'Tiguan', 'Touareg', 'ID.4', 'Arteon'],
        ];

        $brand = $this->faker->randomElement(array_keys($cars));
        $model = $this->faker->randomElement($cars[$brand]);

        return [
            'brand' => $brand,
            'model' => $model,
            'license_plate' => $this->generateDutchLicensePlate(),
        ];
    }

    private function generateDutchLicensePlate(): string
    {
        $formats = [
            'LL-99-LL', // bv. AB-12-CD
            '99-LL-99', // bv. 12-AB-34
            'LL-LL-99', // bv. AB-CD-12
            '99-99-LL', // bv. 12-34-AB
            'LL-999-L', // bv. AB-123-C
            'L-999-LL', // bv. A-123-BC
        ];

        $format = $this->faker->randomElement($formats);
        $plate  = '';

        foreach (str_split($format) as $char) {
            $plate .= match ($char) {
                'L' => strtoupper($this->faker->randomLetter()),
                '9' => $this->faker->numberBetween(0, 9),
                default => $char,
            };
        }

        return $plate;
    }
}
