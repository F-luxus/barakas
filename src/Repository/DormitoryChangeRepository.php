<?php

namespace App\Repository;

use App\Entity\Dormitory;
use App\Entity\DormitoryChange;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method DormitoryChange|null find($id, $lockMode = null, $lockVersion = null)
 * @method DormitoryChange|null findOneBy(array $criteria, array $orderBy = null)
 * @method DormitoryChange[]    findAll()
 * @method DormitoryChange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DormitoryChangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DormitoryChange::class);
    }

    private function findUserOrganisationDormitory($id)
    {
        $entityManager = $this->getEntityManager();

        $dormitoryRepo = $entityManager->getRepository(Dormitory::class);

        $dormitory = $dormitoryRepo->findOneBy(['id' => $id]);

        $dorms = $dormitoryRepo->findBy(['organisation_id' => $dormitory->getOrganisationId()]);

        return $dorms;
    }

    public function removeUserDormitoryFromArray($user, $userDormitoryId)
    {
        $entityManager = $this->getEntityManager();
        $dormitoryRepo = $entityManager->getRepository(Dormitory::class);

        $userDormitory = $dormitoryRepo->getLoggedInUserDormitory($user->getDormId());

        $dorms = $this->findUserOrganisationDormitory($userDormitoryId);

        $key = array_search($userDormitory, $dorms);
        unset($dorms[$key]);

        return $dorms;
    }

    public function findUserAcademy($dormId)
    {
        $entityManager = $this->getEntityManager();
        $dormitoryRepo = $entityManager->getRepository(Dormitory::class);
        $organisationRepo = $entityManager->getRepository(User::class);

        $userDormitory = $dormitoryRepo->findOneBy(
            ['id' => $dormId]
        );

        $organisationId = $userDormitory->getOrganisationId();

        $organisation = $organisationRepo->findOneBy(
            ['id' => $organisationId]
        );

        $academy = $organisation->getAcademy();

        return $academy;
    }

    public function getNotApprovedRequests()
    {
        $requests = $this->findBy(['approved' => false]);

        return $requests;
    }

    public function getDormitoryById($id)
    {
        $request = $this->findOneBy(['id' => $id]);

        return $request;
    }
}
