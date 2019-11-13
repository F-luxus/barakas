<?php

namespace App\Controller;

use App\Entity\Dormitory;
use App\Entity\User;
use App\Entity\Invite;
use App\Form\SendInvitationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends AbstractController
{
    /**
     * @Route("/organisation/admin", name="Admin panel")
     * @param Request $request
     * @return RedirectResponse|Response
     */
    public function index(Request $request, EntityManagerInterface $entityManager)
    {
        
        
        $dormitoryRepository = $this->getDoctrine()->getRepository(Dormitory::class);
        $dormitories = $dormitoryRepository->findAll();

        $id = $request->query->get('id');
        $dormitoryInfo = $dormitoryRepository->find($id);

        $organisationID = $dormitoryInfo->getOrganisationId();

        $dormitory = $dormitoryRepository->findDormitory($organisationID);

        if (!$dormitory) {
            return $this->redirectToRoute('home');
        }
        
        $invitation = new Invite();
        $form = $this->createForm(SendInvitationType::class, $invitation);
        
        $form->handleRequest($request);    
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $invitation->setName($invitation->getName());
            $invitation->setEmail($invitation->getEmail());
            $invitation->setRoom($invitation->getRoom());
            $invitation->setUrl($invitation->generateUrl());
            $invitation->setDorm($dormitoryInfo->getId());
            $entityManager->persist($invitation);
            $entityManager->flush();
            
        }
        
        return $this->render('admin/index.html.twig', [
            'dormitories' => $dormitories,
            'dormitoryInfo' => $dormitoryInfo,
            'dormitory' => $dormitory,
            'SendInvitationType' => $form->createView()
        ]);
    }
}
