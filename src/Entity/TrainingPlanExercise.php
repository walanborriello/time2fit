<?php

namespace App\Entity;

use App\Repository\TrainingPlanExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrainingPlanExerciseRepository::class)]
class TrainingPlanExercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trainingPlanExercises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TrainingPlan $trainingPlan = null;

    #[ORM\ManyToOne(inversedBy: 'trainingPlanExercises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exercise $exercise = null;

    #[ORM\Column]
    private ?int $sets = null;

    #[ORM\Column]
    private ?int $reps = null;

    #[ORM\Column(nullable: true)]
    private ?float $weight = null;

    #[ORM\Column(nullable: true)]
    private ?int $restSeconds = null;

    #[ORM\Column(nullable: true)]
    private ?int $orderIndex = null;

    #[ORM\OneToMany(mappedBy: 'trainingPlanExercise', targetEntity: ProgressLog::class, orphanRemoval: true)]
    private Collection $progressLogs;

    public function __construct()
    {
        $this->progressLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrainingPlan(): ?TrainingPlan
    {
        return $this->trainingPlan;
    }

    public function setTrainingPlan(?TrainingPlan $trainingPlan): static
    {
        $this->trainingPlan = $trainingPlan;
        return $this;
    }

    public function getExercise(): ?Exercise
    {
        return $this->exercise;
    }

    public function setExercise(?Exercise $exercise): static
    {
        $this->exercise = $exercise;
        return $this;
    }

    public function getSets(): ?int
    {
        return $this->sets;
    }

    public function setSets(int $sets): static
    {
        $this->sets = $sets;
        return $this;
    }

    public function getReps(): ?int
    {
        return $this->reps;
    }

    public function setReps(int $reps): static
    {
        $this->reps = $reps;
        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): static
    {
        $this->weight = $weight;
        return $this;
    }

    public function getRestSeconds(): ?int
    {
        return $this->restSeconds;
    }

    public function setRestSeconds(?int $restSeconds): static
    {
        $this->restSeconds = $restSeconds;
        return $this;
    }

    public function getOrderIndex(): ?int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(?int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;
        return $this;
    }

    /**
     * @return Collection<int, ProgressLog>
     */
    public function getProgressLogs(): Collection
    {
        return $this->progressLogs;
    }

    public function addProgressLog(ProgressLog $progressLog): static
    {
        if (!$this->progressLogs->contains($progressLog)) {
            $this->progressLogs->add($progressLog);
            $progressLog->setTrainingPlanExercise($this);
        }

        return $this;
    }

    public function removeProgressLog(ProgressLog $progressLog): static
    {
        if ($this->progressLogs->removeElement($progressLog)) {
            if ($progressLog->getTrainingPlanExercise() === $this) {
                $progressLog->setTrainingPlanExercise(null);
            }
        }

        return $this;
    }
}

