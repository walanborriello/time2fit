<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'client', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: InstructorClient::class, orphanRemoval: true)]
    private Collection $instructorClients;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: TrainingPlan::class)]
    private Collection $trainingPlans;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Appointment::class)]
    private Collection $appointments;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
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
            $instructorClient->setClient($this);
        }

        return $this;
    }

    public function removeInstructorClient(InstructorClient $instructorClient): static
    {
        if ($this->instructorClients->removeElement($instructorClient)) {
            if ($instructorClient->getClient() === $this) {
                $instructorClient->setClient(null);
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
            $trainingPlan->setClient($this);
        }

        return $this;
    }

    public function removeTrainingPlan(TrainingPlan $trainingPlan): static
    {
        if ($this->trainingPlans->removeElement($trainingPlan)) {
            if ($trainingPlan->getClient() === $this) {
                $trainingPlan->setClient(null);
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
            $appointment->setClient($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            if ($appointment->getClient() === $this) {
                $appointment->setClient(null);
            }
        }

        return $this;
    }
}

