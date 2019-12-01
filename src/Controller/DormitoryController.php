<?php

namespace App\Controller;

use App\Entity\Dormitory;
use App\Entity\Help;
use App\Entity\Message;
use App\Entity\Notification;
use App\Entity\SolvedType;
use App\Entity\StatusType;
use App\Form\MessageType;
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
     * @return Response
     * @throws Exception
     */
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        StudentManager $studentManager
    ) {
        $user = $this->getUser();

        $dormitoryRepo = $this->getDoctrine()->getRepository(Dormitory::class);

        $dormitory = $dormitoryRepo->getLoggedInUserDormitory($user->getDormId());
        $students = $dormitoryRepo->getStudentsInDormitory($user->getDormId());
        $messages = $dormitoryRepo->getDormitoryMessages($user->getDormId());

        $message = new Message();
        $formRequest = $this->createForm(MessageType::class, $message);

        $formRequest->handleRequest($request);

        if ($formRequest->isSubmitted() && $formRequest->isValid()) {
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


        return $this->render('dormitory/index.html.twig', [
            'dormitory' => $dormitory,
            'students' => $students,
            'messages' => $messages,
            'formRequest' => $formRequest->createView(),
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
}
