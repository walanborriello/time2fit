<?php

namespace App\Entity;

use App\Repository\InstructorProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstructorProfileRepository::class)]
class InstructorProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'instructorProfile', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $bio = null;

    #[ORM\OneToMany(mappedBy: 'instructor', targetEntity: InstructorClient::class, orphanRemoval: true)]
    private Collection $instructorClients;

    #[ORM\OneToMany(mappedBy: 'instructor', targetEntity: TrainingPlan::class)]
    private Collection $trainingPlans;

    #[ORM\OneToMany(mappedBy: 'instructor', targetEntity: Appointment::class, orphanRemoval: true)]
    private Collection $appointments;

    public function __construct()
    {
        $this->instructorClients = new ArrayCollection();
        $this->trainingPlans = new ArrayCollection();
        $this->appointments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    /**
     * @return Collection<int, InstructorClient>
     */
    public function getInstructorClients(): Collection
    {
        return $this->instructorClients;
    }

    public function addInstructorClient(InstructorClient $instructorClient): static
    {
        if (!$this->instructorClients->contains($instructorClient)) {
            $this->instructorClients->add($instructorClient);
            $instructorClient->setInstructor($this);
        }

        return $this;
    }

    public function removeInstructorClient(InstructorClient $instructorClient): static
    {
        if ($this->instructorClients->removeElement($instructorClient)) {
            if ($instructorClient->getInstructor() === $this) {
                $instructorClient->setInstructor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TrainingPlan>
     */
    public function getTrainingPlans(): Collection
    {
        return $this->trainingPlans;
    }

    public function addTrainingPlan(TrainingPlan $trainingPlan): static
    {
        if (!$this->trainingPlans->contains($trainingPlan)) {
            $this->trainingPlans->add($trainingPlan);
            $trainingPlan->setInstructor($this);
        }

        return $this;
    }

    public function removeTrainingPlan(TrainingPlan $trainingPlan): static
    {
        if ($this->trainingPlans->removeElement($trainingPlan)) {
            if ($trainingPlan->getInstructor() === $this) {
                $trainingPlan->setInstructor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): static
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setInstructor($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getInstructor() === $this) {
                $appointment->setInstructor(null);
            }
        }

        return $this;
    }
}

