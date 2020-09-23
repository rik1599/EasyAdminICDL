<?php

namespace App\Repository;

use App\Entity\Session;
use App\Entity\SkillCard;
use App\Enum\EnumBookingStatus;
use App\Enum\EnumSessionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Session|null find($id, $lockMode = null, $lockVersion = null)
 * @method Session|null findOneBy(array $criteria, array $orderBy = null)
 * @method Session[]    findAll()
 * @method Session[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    public function getAvailableSessionsForSkillCard(SkillCard $skillCard)
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.subscribeExpireDate >= :date')
            ->andWhere('s.certification = :cert')
            ->setParameters([
                'status' => EnumSessionStatus::ACTIVATED,
                'date' => (new \DateTime())->format('Y-m-d'),
                'cert' => $skillCard->getCertification()
            ])->getQuery()->getResult();
    }

    public function getBookedSessions(SkillCard $skillCard)
    {

    }
    // /**
    //  * @return Session[] Returns an array of Session objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Session
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}