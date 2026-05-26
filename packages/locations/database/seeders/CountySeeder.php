<?php

namespace Lunar\Locations\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Locations\Models\County;

class CountySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $counties = [
            ['id' => 1, 'name' => 'Alba', 'code' => 'AB'],
            ['id' => 2, 'name' => 'Arad', 'code' => 'AR'],
            ['id' => 3, 'name' => 'Arges', 'code' => 'AG'],
            ['id' => 4, 'name' => 'Bacau', 'code' => 'BC'],
            ['id' => 5, 'name' => 'Bihor', 'code' => 'BH'],
            ['id' => 6, 'name' => 'Bistrita-Nasaud', 'code' => 'BN'],
            ['id' => 7, 'name' => 'Botosani', 'code' => 'BT'],
            ['id' => 8, 'name' => 'Brasov', 'code' => 'BV'],
            ['id' => 9, 'name' => 'Braila', 'code' => 'BR'],
            ['id' => 10, 'name' => 'Buzau', 'code' => 'BZ'],
            ['id' => 11, 'name' => 'Caras-Severin', 'code' => 'CS'],
            ['id' => 12, 'name' => 'Calarasi', 'code' => 'CL'],
            ['id' => 13, 'name' => 'Cluj', 'code' => 'CJ'],
            ['id' => 14, 'name' => 'Constanta', 'code' => 'CT'],
            ['id' => 15, 'name' => 'Covasna', 'code' => 'CV'],
            ['id' => 16, 'name' => 'Dambovita', 'code' => 'DB'],
            ['id' => 17, 'name' => 'Dolj', 'code' => 'DJ'],
            ['id' => 18, 'name' => 'Galati', 'code' => 'GL'],
            ['id' => 19, 'name' => 'Giurgiu', 'code' => 'GR'],
            ['id' => 20, 'name' => 'Gorj', 'code' => 'GJ'],
            ['id' => 21, 'name' => 'Harghita', 'code' => 'HR'],
            ['id' => 22, 'name' => 'Hunedoara', 'code' => 'HD'],
            ['id' => 23, 'name' => 'Ialomita', 'code' => 'IL'],
            ['id' => 24, 'name' => 'Iasi', 'code' => 'IS'],
            ['id' => 25, 'name' => 'Ilfov', 'code' => 'IF'],
            ['id' => 26, 'name' => 'Maramures', 'code' => 'MM'],
            ['id' => 27, 'name' => 'Mehedinti', 'code' => 'MH'],
            ['id' => 28, 'name' => 'Mures', 'code' => 'MS'],
            ['id' => 29, 'name' => 'Neamt', 'code' => 'NT'],
            ['id' => 30, 'name' => 'Olt', 'code' => 'OT'],
            ['id' => 31, 'name' => 'Prahova', 'code' => 'PH'],
            ['id' => 32, 'name' => 'Satu Mare', 'code' => 'SM'],
            ['id' => 33, 'name' => 'Salaj', 'code' => 'SJ'],
            ['id' => 34, 'name' => 'Sibiu', 'code' => 'SB'],
            ['id' => 35, 'name' => 'Suceava', 'code' => 'SV'],
            ['id' => 36, 'name' => 'Teleorman', 'code' => 'TR'],
            ['id' => 37, 'name' => 'Timis', 'code' => 'TM'],
            ['id' => 38, 'name' => 'Tulcea', 'code' => 'TL'],
            ['id' => 39, 'name' => 'Vaslui', 'code' => 'VS'],
            ['id' => 40, 'name' => 'Valcea', 'code' => 'VL'],
            ['id' => 41, 'name' => 'Vrancea', 'code' => 'VN'],
            ['id' => 42, 'name' => 'Bucuresti', 'code' => 'B'],
        ];

        foreach ($counties as $county) {
            County::create([
                'id' => $county['id'],
                'name' => $county['name'],
                'code' => $county['code'],
                'country_id' => 182,
            ]);
        }
    }
}
