<?php

namespace App\Entity;

use App\Repository\TrainingPlanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingPlanRepository::class)]
class TrainingPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'trainingPlans')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(inversedBy: 'trainingPlans')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InstructorProfile $instructor = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $expiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'trainingPlan', targetEntity: TrainingPlanExercise::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $trainingPlanExercises;

    #[ORM\Column]
    private ?bool $isActive = true;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->trainingPlanExercises = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getInstructor(): ?InstructorProfile
    {
        return $this->instructor;
    }

    public function setInstructor(?InstructorProfile $instructor): static
    {
        $this->instructor = $instructor;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeInterface $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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
     * @return Collection<int, TrainingPlanExercise>
     */
    public function getTrainingPlanExercises(): Collection
    {
        return $this->trainingPlanExercises;
    }

    public function addTrainingPlanExercise(TrainingPlanExercise $trainingPlanExercise): static
    {
        if (!$this->trainingPlanExercises->contains($trainingPlanExercise)) {
            $this->trainingPlanExercises->add($trainingPlanExercise);
            $trainingPlanExercise->setTrainingPlan($this);
        }

        return $this;
    }

    public function removeTrainingPlanExercise(TrainingPlanExercise $trainingPlanExercise): static
    {
        if ($this->trainingPlanExercises->removeElement($trainingPlanExercise)) {
            if ($trainingPlanExercise->getTrainingPlan() === $this) {
                $trainingPlanExercise->setTrainingPlan(null);
            }
        }

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime();
    }
}

