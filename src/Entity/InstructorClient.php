<?php

namespace App\Entity;

use App\Repository\InstructorClientRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InstructorClientRepository::class)]
#[ORM\Table(name: 'instructor_client')]
#[ORM\UniqueConstraint(name: 'unique_instructor_client', columns: ['instructor_id', 'client_id'])]
class InstructorClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'instructorClients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?InstructorProfile $instructor = null;

    #[ORM\ManyToOne(inversedBy: 'instructorClients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $assignedAt = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    public function __construct()
    {
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;
        return $this;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function setAssignedAt(\DateTimeImmutable $assignedAt): static
    {
        $this->assignedAt = $assignedAt;
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
}

