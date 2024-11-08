<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Form\TripFilterType;
use App\Form\TripType;
use App\Models\TripFilterModel;
use App\Repository\StateRepository;
use App\Repository\TripRepository;
use App\Service\Filters\TripFilterService;
use App\Service\Trip\DateTimeTripService;
use App\Service\Trip\DeleteTripService;
use App\Service\Trip\NewTripService;
use App\Service\Trip\RefreshTripService;
use App\Service\Trip\TripService;
use Container3AjzDap\getStateRepositoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use function PHPUnit\Framework\isNull;

#[Route('/trip')]
final class TripController extends AbstractController
{
    #[Route(name: 'app_trip_index', methods: ['GET', 'POST'])]
    public function index(RefreshTripService $refreshTripService, Request $request, TripRepository $tripRepository, TripFilterService $tripFilterService): Response
    {
        $refreshTripService->refreshTrip();

        $filterChoices = new TripFilterModel();
        $form = $this->createForm(TripFilterType::class, $filterChoices);
        $form->handleRequest($request);

        return $this->render('trip/index.html.twig', [
            'trips' => $tripFilterService->getTripWithFilters($filterChoices),
            'form' => $form,
            //'choices' => $filterChoices,
        ]);
    }

    #[Route('/new', name: 'app_trip_new', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_USER")]
    public function new(Request $request,TripService $tripService, EntityManagerInterface $entityManager): Response
    {
        $trip = new Trip();

        $form = $this->createForm(TripType::class, $trip);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trip->setOrganizer($this->getUser());
            if ($request->request->has('save')) {
                $message = $tripService->setTripState($trip, "Créée");
            } else {
                $message = $tripService->setTripState($trip, "Ouverte");
            }

            $this->addFlash($message[0] , $message[1]);

            $entityManager->persist($trip);
            $entityManager->flush();

            return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('trip/new.html.twig', [
            'trip' => $trip,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_trip_show', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_USER")]
    public function show(Trip $trip, Request $request, TripService $tripService): Response
    {
        if ($this->getUser() != null && $request->getMethod() == 'POST') {
            $userInSession = $this->getUser();
            $message = $tripService->addAParticipant($trip);

            $this->addFlash($message[0], $message[1]);
        }
        return $this->render('trip/show.html.twig', [
            'trip' => $trip,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_trip_edit', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_USER")]
    public function edit(TripService $tripService, Request $request, Trip $trip, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() != $trip->getOrganizer()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TripType::class, $trip);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($request->request->has('save')) {
                $tripService->setTripState($trip, "Créée");


            }
            if ($request->request->has('delete')) {
                $tripService->deleteTrip($trip);

            } else {
                $tripService->setTripState($trip, "Ouverte");
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('trip/edit.html.twig', [
            'trip' => $trip,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_trip_delete', methods: ['POST'])]
    #[IsGranted("ROLE_USER")]
    public function delete(Request $request, Trip $trip, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() != $trip->getOrganizer()) {
            throw $this->createAccessDeniedException();
        }
        if ($this->isCsrfTokenValid('delete' . $trip->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($trip);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/publish', name: 'app_trip_publish', methods: ['GET'])]
    #[IsGranted("ROLE_USER")]
    public function publish(Trip $trip, TripService $tripService): Response
    {
        $tripService->setTripState($trip, 'Ouverte');
        return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/participate', name: 'app_trip_participate', methods: ['GET'])]
    #[IsGranted("ROLE_USER")]
    public function participate(Trip $trip, TripService $tripService): Response
    {
        if ($this->getUser() !== $trip->getOrganizer()) {
            $message = $tripService->addAParticipant($trip);
        }
        if (count($message)>0){
            $this->addFlash($message[0] , $message[1]);}

        return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/desist', name: 'app_trip_desist', methods: ['GET'])]
    #[IsGranted("ROLE_USER")]
    public function desist(Trip $trip, TripService $tripService): Response
    {
        if ($this->getUser() !== $trip->getOrganizer()) {
            $message = $tripService->removeAParticipant($trip);
        }
        if (count($message)>0){
            $this->addFlash($message[0] , $message[1]);}
        return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/cancel', name: 'app_trip_cancel', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_USER")]
    public function cancel(TripService $tripService, Request $request, Trip $trip): Response
    {
        if ($this->getUser() != $trip->getOrganizer()) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $reason = $request->request->get('reason');
            $message = $tripService->cancelTrip($trip, $reason);

            if (count($message) > 0) {
                $this->addFlash($message[0], $message[1]);
            }
                return $this->redirectToRoute('app_trip_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('trip/cancel.html.twig', [
            'trip' => $trip,
        ]);
    }
}
