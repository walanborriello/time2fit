<?php

namespace App\Entity;

use App\Repository\ExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
class Exercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $mediaUrl = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $mediaType = null;

    #[ORM\ManyToOne(inversedBy: 'exercises')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ExerciseSet $exerciseSet = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $muscleGroup = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aiPrompt = null;

    #[ORM\OneToMany(mappedBy: 'exercise', targetEntity: TrainingPlanExercise::class)]
    private Collection $trainingPlanExercises;

    public function __construct()
    {
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMediaUrl(): ?string
    {
        return $this->mediaUrl;
    }

    public function setMediaUrl(?string $mediaUrl): static
    {
        $this->mediaUrl = $mediaUrl;
        return $this;
    }

    public function getMediaType(): ?string
    {
        return $this->mediaType;
    }

    public function setMediaType(?string $mediaType): static
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    public function getExerciseSet(): ?ExerciseSet
    {
        return $this->exerciseSet;
    }

    public function setExerciseSet(?ExerciseSet $exerciseSet): static
    {
        $this->exerciseSet = $exerciseSet;
        return $this;
    }

    public function getMuscleGroup(): ?string
    {
        return $this->muscleGroup;
    }

    public function setMuscleGroup(?string $muscleGroup): static
    {
        $this->muscleGroup = $muscleGroup;
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
            $trainingPlanExercise->setExercise($this);
        }

        return $this;
    }

    public function removeTrainingPlanExercise(TrainingPlanExercise $trainingPlanExercise): static
    {
        if ($this->trainingPlanExercises->removeElement($trainingPlanExercise)) {
            if ($trainingPlanExercise->getExercise() === $this) {
                $trainingPlanExercise->setExercise(null);
            }
        }

        return $this;
    }

    public function getAiPrompt(): ?string
    {
        return $this->aiPrompt;
    }

    public function setAiPrompt(?string $aiPrompt): static
    {
        $this->aiPrompt = $aiPrompt;
        return $this;
    }
}

