<?php

namespace App\Controller;

use App\Entity\Dormitory;
use App\Entity\Help;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\SolvedType;
use App\Entity\StatusType;
use App\Form\MessageType;
use App\Service\DormitoryService;
use App\Service\StudentManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DormitoryController extends AbstractController
{
    /**
     * @Route("/dormitory", name="dormitory")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param StudentManager $studentManager
     * @param DormitoryService $dormitoryService
     * @return Response
     * @throws Exception
     */
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        StudentManager $studentManager,
        DormitoryService $dormitoryService
    ) {
        $user = $this->getUser();
        $dormitoryRepo = $this->getDoctrine()->getRepository(Dormitory::class);

        $dormitory = $dormitoryRepo->getLoggedInUserDormitory($user->getDormId());
        $students = $dormitoryRepo->orderTopStudentsByPoints($user->getDormId());
        $messages = $dormitoryRepo->getDormitoryMessages($user->getDormId());

        $message = new Message();
        $formRequest = $this->createForm(MessageType::class, $message);

        $formRequest->handleRequest($request);

        if ($formRequest->isSubmitted() && $formRequest->isValid()) {
            if (!$dormitoryService->canSendMessage($user)) {
                $this->addFlash('error', 'Jūs katik siuntėte pranešimą, bandykite vėl po 2 minučių.');
                return $this->redirectToRoute('dormitory');
            }

            $message->setUser($user);
            $message->setDormId($user->getDormId());
            $message->setRoomNr($user->getRoomNr());
            $message->setContent($message->getContent());
            $message->setStatus(StatusType::urgent());
            $message->setSolved(SolvedType::notSolved());

            $entityManager->persist($message);
            $entityManager->flush();

            $students = $studentManager->removeStudentFromStudentsArray($students, $user);

            foreach ($students as $student) {
                $notification = new Notification();
                $notification->setUser($message->getUser());
                $notification->setCreatedAt(new \DateTime());
                $notification->setRoomNr($message->getRoomNr());
                $notification->setDormId($message->getDormId());
                $notification->setContent($message->getContent());
                $notification->setRecipientId($student->getId());
                $notification->setMessage($message);
                $entityManager->persist($notification);
                $entityManager->flush();
            }

            $this->addFlash('success', 'Prašymas išsiųstas sėkmingai!');
            return $this->redirectToRoute('dormitory');
        }

        if ($formRequest->isSubmitted() && !$formRequest->isValid()) {
            $this->addFlash('error', 'Prašymas nebuvo išsiųstas. Prašymas turi sudaryti nuo 7 iki 300 simbolių.');
            return $this->redirectToRoute('dormitory');
        }

        $loggedInUsers = $dormitoryService->getAllLoggedInUsers($user);

        return $this->render('dormitory/index.html.twig', [
            'dormitory' => $dormitory,
            'students' => $students,
            'messages' => $messages,
            'formRequest' => $formRequest->createView(),
            'loggedInUsers' => $loggedInUsers,
        ]);
    }

    /**
     * @Route("dormitory/message/{id}", name="message")
     * @param $id
     * @return Response
     */
    public function showMessage($id)
    {
        $user = $this->getUser();
        $messagesRepo = $this->getDoctrine()->getRepository(Message::class);
        $dormitoryRepo = $this->getDoctrine()->getRepository(Dormitory::class);

        $message = $messagesRepo->find($id);

        $dormitory = $dormitoryRepo->getLoggedInUserDormitory($user->getDormId());
        $students = $dormitoryRepo->getStudentsInDormitory($user->getDormId());

        if (!$message || $user->getDormId() !== $message->getDormId()) {
            return $this->redirectToRoute('dormitory');
        }

        return $this->render('dormitory/message.html.twig', [
            'message' => $message,
            'dormitory' => $dormitory,
            'students' => $students
        ]);
    }

    /**
     * @Route("/dormitory/help/{id}", name="dormitory_help")
     * @param $id
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse
     */
    public function helpUser($id, EntityManagerInterface $entityManager)
    {
        $user = $this->getUser();
        $messagesRepo = $this->getDoctrine()->getRepository(Message::class);
        $dormitoryRepo = $this->getDoctrine()->getRepository(Dormitory::class);
        $helpRepo = $this->getDoctrine()->getRepository(Help::class);

        $message = $messagesRepo->find($id);
        $dormitory = $dormitoryRepo->getLoggedInUserDormitory($user->getDormId());
        $help = $helpRepo->findUserProvidedHelp($message->getUser()->getId(), $user->getId(), $message);

        if (!$message || $help) {
            return $this->redirectToRoute('dormitory');
        }

        $help = new Help();
        $help->setMessage($message);
        $help->setUser($user);
        $help->setDormId($dormitory->getId());
        $help->setRoomNr($user->getRoomNr());
        $help->setRequesterId($message->getUser()->getId());

        $entityManager->persist($help);
        $message->setSolved(SolvedType::solved());
        $message->setSolver($user);

        $entityManager->flush();


        $this->addFlash('success', 'Pagalbos siūlymas išsiųstas sėkmingai!');

        return $this->redirectToRoute('dormitory');
    }

    /**
     * @Route("dormitory/rules", name="rules")
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function rules(EntityManagerInterface $entityManager)
    {
        $user = $this->getUser();
        $dorm_id = $user->getDormId();
        $dorm = $entityManager->getRepository(Dormitory::class)->find($dorm_id);
        return $this->render('dormitory/rules.html.twig', [
            'rules' => $dorm->getRules()
        ]);
    }

    /**
     * @Route("/dormitory/report/message", name="reportMessage")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse
     */
    public function reportMessage(Request $request, EntityManagerInterface $entityManager)
    {
        $reportMessageId = $request->get('id');
        $messageRepository = $this->getDoctrine()->getRepository(Message::class);
        $message = $messageRepository->find($reportMessageId);

        if (!$message) {
            return $this->redirectToRoute('dormitory');
        }

        $message->setReported(true);
        $entityManager->flush();

        $this->addFlash('success', 'Apie netinkamą pranešimą pranešta administracijai.');

        return $this->redirectToRoute('dormitory');
    }

    /**
     * @Route("/dormitory/accept-help-request", name="acceptHelp")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param DormitoryService $dormitoryService
     * @return RedirectResponse
     */
    public function acceptHelpRequest(
        Request $request,
        EntityManagerInterface $entityManager,
        DormitoryService $dormitoryService
    ) {
        $helpId = $request->get('id');
        $messageId = $request->get('msg');
        $helpRepository = $this->getDoctrine()->getRepository(Help::class);
        $messageRepository = $this->getDoctrine()->getRepository(Message::class);
        $message = $messageRepository->findOneBy(['id'=> $messageId]);
        $userWhoHelped = $helpRepository->findUserWhoProvidedHelp($helpId);
        $userWhoHelpedPoints = $userWhoHelped->getPoints();
        $newPoints = $userWhoHelpedPoints + $dormitoryService->
            calculateRewardPoints($message->getCreatedAt(), 500);

        $userWhoHelped->setPoints($newPoints);

        $help = $helpRepository->find($helpId);

        $entityManager->remove($help);
        $entityManager->flush();

        $this->addFlash('success', 'Pagalbos siūlymas patvirtintas.');


        return $this->redirectToRoute('provided_help');
    }

    /**
     * @Route("/dormitory/deny-help-request", name="denyHelp")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return RedirectResponse
     */
    public function denyHelpRequest(Request $request, EntityManagerInterface $entityManager)
    {
        $helpId = $request->get('id');
        $helpRepository = $this->getDoctrine()->getRepository(Help::class);

        $message = $helpRepository->findMessageFromHelp($helpId);
        $message->setSolved(SolvedType::notSolved());
        $message->setSolver(null);

        $help = $helpRepository->find($helpId);

        $entityManager->remove($help);
        $entityManager->flush();

        $this->addFlash('success', 'Pagalbos siūlymas pašalintas, pranešimas gražintas į pradinę stadiją.');

        return $this->redirectToRoute('provided_help');
    }

    /**
     * @Route("/dormitory/students", name="dormitory_leaderboard")
     */
    public function allDormitoryStudents()
    {
        $user = $this->getUser();

        $dormitoryRepo = $this->getDoctrine()->getRepository(Dormitory::class);

        $dormitory = $dormitoryRepo->getLoggedInUserDormitory($user->getDormId());
        $students = $dormitoryRepo->orderAllStudentsByPoints($user->getDormId());

        return $this->render('/dormitory/dormitory_leaderboard.html.twig', [
            'students' => $students,
            'dormitory' => $dormitory
        ]);
    }
}
