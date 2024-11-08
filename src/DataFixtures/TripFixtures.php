<?php

namespace App\DataFixtures;

use App\Entity\Trip;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class TripFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');
        for ($i = 0; $i < 100; $i++) {
            $trip = new Trip();
            $trip->setName($faker->words(5, true));
            //$date = $faker->dateTime();
            $date =$faker->dateTimeBetween('-1 week', '+1 week');
            $trip->setStartDateTime($date);
            $trip->setDuration($faker->numberBetween(30, 240));
            $registrationDeadline = clone $date;
            $registrationDeadline->modify('-24 hours');
            $trip->setRegistrationDeadline($registrationDeadline);
            $trip->setNbRegistrationMax($faker->numberBetween(2, 150));
            $trip->setInfo($faker->paragraph());
            $trip->setState($this->getReference('state_'.rand(0,5)));
            $trip->setRelativeCampus($this->getReference("campus_" .rand(0, 4)));
            $trip->addParticipant($this->getReference("participant_" .rand(0, 4)));
            $trip->addParticipant($this->getReference("participant_" .rand(5, 9)));
            $trip->setOrganizer($this->getReference("participant_" .rand(0, 9)));
            $trip->setPlace($this->getReference("place_" .rand(0, 9)));
            $this->addReference("trip_" . $i, $trip);
            $manager->persist($trip);
        }

        $manager->flush();
    }

    public function getDependencies()
    {
        return [ParticipantFixtures::class, StateFixtures::class, CampusFixtures::class];
    }
}
