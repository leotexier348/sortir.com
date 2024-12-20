<?php

namespace App\Repository;

use App\Entity\Participant;
use App\Entity\Trip;
use App\Form\TripFilterType;
use App\Models\TripFilterModel;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use mysql_xdevapi\Statement;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    public function findTripRefresh()
    {
//        On récupère tout les évenements qui sont a 1mois et 1 jour pour avoir le temps de mettre à jour les
//        évenement le jour ou il passe de passé à historisé. Dans ce cas on part du principe qu'il y aura au minimum
//        1 refresh par jour, se sera surement + dans la réalité.
        $dateTime = new DateTime("now");
        $dateTime->modify('-1 month -1 day');
        $querybuilder = $this->createQueryBuilder('trip')
            ->where('trip.startDateTime >= :dateTime')
            ->setParameter('dateTime', $dateTime);
        $query = $querybuilder->getQuery();
        return new Paginator($query);
    }

    public function findTripByFilters(TripFilterModel $filterChoices, Participant $userInSession): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.state', 's')
            ->addSelect('s')
            ->leftJoin('t.place', 'pl')
            ->addSelect('pl')
            ->leftJoin('pl.city', 'ci')
            ->addSelect('ci')
            ->leftJoin('t.relativeCampus', 'rc')
            ->addSelect('rc')
            ->leftJoin('t.participants', 'pa')
            ->addSelect('pa')
            ->leftJoin('pa.campus', 'pac')
            ->addSelect('pac')
            ->andWhere('s != :stateH')
            ->setParameter('stateH', STATE_HISTORICIZED);

        if ($filterChoices->getRelativeCampus() !== null) {
            $qb->andWhere('rc = :campus')
                ->setParameter('campus', $filterChoices->getRelativeCampus());
        }

        if (!empty($filterChoices->getTripName())) {
            $qb->andWhere('t.name LIKE :tripName')
                ->setParameter('tripName', '%' . $filterChoices->getTripName() . '%');
        }

        if (!empty($filterChoices->getStartDateTime())) {
            $stringStartDateTime = $filterChoices->getStartDateTime()->format('Y-m-d H:i:s');
            $qb->andWhere('t.startDateTime >= :startDateTime')
                ->setParameter('startDateTime', $stringStartDateTime);
        }

        if (!empty($filterChoices->getRegistrationDeadline())) {
            $stringregistrationDeadline = $filterChoices->getRegistrationDeadline()->format('Y-m-d H:i:s');
            $qb->andWhere('t.registrationDeadline <= :registrationDeadline')
                ->setParameter('registrationDeadline', $stringregistrationDeadline);
        }

        if ($filterChoices->getIOrganized()) {
            $qb->andWhere('t.organizer = :organizer')
                ->setParameter('organizer', $userInSession);
        }


        if ($filterChoices->getIParticipate() && $filterChoices->getImRegistered()) {
            $qb->andWhere('s.wording = :state')
                ->orWhere('t.organizer = :organizer')
                ->setParameter('state', STATE_OPEN)
                ->setParameter('organizer', $userInSession);
        } else if ($filterChoices->getIParticipate()) {
            $qb
                ->andWhere(':participants MEMBER OF t.participants')
                ->setParameter('participants', $userInSession);
        } else if ($filterChoices->getImRegistered()) {
            $qb
                ->andWhere(':participants NOT MEMBER OF t.participants')
                ->setParameter('participants', $userInSession)
                ->andWhere('s.wording = :state')
                ->setParameter('state', STATE_OPEN);
        }

        if ($filterChoices->getOldTrips()) {
            $now = (new dateTime())->format('Y-m-d H:i:s');
            // $dateEnd = $startDateTime + $duration
            $qb->andWhere('t.registrationDeadline < :registrationDeadline')
                ->setParameter('registrationDeadline', $now);
        }

        return $qb->getQuery()->getResult();
    }

}
