<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorCategory;
use Illuminate\Database\Seeder;

/** ~50 example visitors with Georgian names, of every age group. */
class VisitorSeeder extends Seeder
{
    /** [Georgian, Latin] */
    protected const MALE = [
        ['გიორგი', 'giorgi'], ['ლუკა', 'luka'], ['დავით', 'davit'], ['ნიკა', 'nika'], ['ლევან', 'levan'],
        ['ირაკლი', 'irakli'], ['სანდრო', 'sandro'], ['ალექსანდრე', 'aleksandre'], ['ზურაბ', 'zurab'], ['თორნიკე', 'tornike'],
        ['გიგა', 'giga'], ['ბექა', 'beka'], ['საბა', 'saba'], ['დემეტრე', 'demetre'], ['ვახტანგ', 'vakhtang'],
    ];

    protected const FEMALE = [
        ['ნინო', 'nino'], ['მარიამ', 'mariam'], ['ანა', 'ana'], ['თამარ', 'tamar'], ['ელენე', 'elene'],
        ['ნათია', 'natia'], ['სალომე', 'salome'], ['ქეთევან', 'ketevan'], ['მაკა', 'maka'], ['ლიკა', 'lika'],
        ['ეკატერინე', 'ekaterine'], ['ნანა', 'nana'], ['თეონა', 'teona'], ['ბარბარე', 'barbare'], ['სოფიო', 'sopio'],
    ];

    protected const SURNAMES = [
        ['ბერიძე', 'beridze'], ['კაპანაძე', 'kapanadze'], ['გელაშვილი', 'gelashvili'], ['მაისურაძე', 'maisuradze'],
        ['ლომიძე', 'lomidze'], ['ხუციშვილი', 'khutsishvili'], ['ჯავახიშვილი', 'javakhishvili'], ['ნოზაძე', 'nozadze'],
        ['ჩიქოვანი', 'chikovani'], ['წიკლაური', 'tsiklauri'], ['გოგიჩაიშვილი', 'gogichaishvili'], ['მჭედლიშვილი', 'mchedlishvili'],
        ['ქავთარაძე', 'kavtaradze'], ['ბაქრაძე', 'bakradze'], ['ასათიანი', 'asatiani'], ['კვარაცხელია', 'kvaratskhelia'],
        ['თოდუა', 'todua'], ['შენგელია', 'shengelia'], ['ჭავჭავაძე', 'chavchavadze'], ['მამულაშვილი', 'mamulashvili'],
    ];

    protected const COMMENTS = [
        'პირველი ვიზიტი, ინსტრუქტაჟი გაიარა',
        'მუხლის ტრავმა ჰქონდა, ფრთხილად',
        'მოყავს მშობელი',
        'მოსწონს ბოულდერინგი',
        'ასთმა — ინჰალატორი თან აქვს',
    ];

    public function run(int $count = 50): void
    {
        $regular = VisitorCategory::where('code', 'regular')->value('id');
        $member = VisitorCategory::where('code', 'member')->value('id');
        $staffId = User::whereHas('roles', fn ($q) => $q->where('name', 'worker'))->value('id');

        for ($i = 0; $i < $count; $i++) {
            $female = $i % 2 === 1;
            [$firstKa, $firstLat] = fake()->randomElement($female ? self::FEMALE : self::MALE);
            [$lastKa, $lastLat] = fake()->randomElement(self::SURNAMES);

            // Age mix: ~15% kids (4–9), ~20% teens (10–15), the rest adults.
            $age = match (true) {
                $i % 7 === 0 => fake()->numberBetween(4, 9),
                $i % 5 === 0 => fake()->numberBetween(10, 15),
                default => fake()->numberBetween(16, 55),
            };
            $birthDate = now()->subYears($age)->subDays(fake()->numberBetween(1, 360));

            $createdAt = now()->subDays(fake()->numberBetween(60, 120))->setTime(fake()->numberBetween(9, 21), fake()->numberBetween(0, 59));

            Visitor::create([
                'first_name' => $firstKa,
                'last_name' => $lastKa,
                // Georgian personal number: 11 digits.
                'personal_id' => fake()->unique()->numerify('0'.fake()->randomElement(['1', '3', '6']).'#########'),
                'birth_date' => $birthDate->toDateString(),
                'phone' => '5'.fake()->randomElement(['55', '57', '58', '59', '91', '93', '95', '98']).fake()->numerify('######'),
                'email' => $age >= 16 && fake()->boolean(45) ? $firstLat.'.'.$lastLat.fake()->numberBetween(1, 99).'@'.fake()->randomElement(['gmail.com', 'yahoo.com', 'mail.ru']) : null,
                'comment' => fake()->boolean(15) ? fake()->randomElement(self::COMMENTS) : null,
                'visitor_category_id' => $age >= 18 && fake()->boolean(20) ? $member : $regular,
                'terms_accepted' => true,
                'terms_accepted_at' => $createdAt,
                'created_by' => $staffId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        // Make the test user's account show a gym card.
        if ($user = User::where('email', 'user@test.loc')->first()) {
            $visitor = Visitor::whereDoesntHave('userLink')->whereDate('birth_date', '<', now()->subYears(18))->first();
            if ($visitor && ! $user->visitorLink()->exists()) {
                $user->forceFill(['personal_id' => $visitor->personal_id])->save();
                $user->visitorLink()->create(['visitor_id' => $visitor->id, 'linked_via' => 'personal_id']);
            }
        }

        $this->command?->info("{$count} visitors created.");
    }
}
