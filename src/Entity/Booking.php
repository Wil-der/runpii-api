<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'booking')]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['api:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['pending', 'accepted', 'in_progress', 'delivered', 'cancelled'])]
    #[Groups(['api:read', 'api:write'])]
    private ?string $status = 'pending';

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['api:read', 'api:write'])]
    private ?string $pickupAddress = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['api:read', 'api:write'])]
    private ?string $deliveryAddress = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?float $pickupLatitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?float $pickupLongitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?float $deliveryLatitude = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?float $deliveryLongitude = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?float $estimatedPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?float $finalPrice = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?int $estimatedDuration = null; // en minutos

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?int $actualDuration = null; // en minutos

    #[ORM\Column]
    #[Groups(['api:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?\DateTimeImmutable $pickedUpAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $cancelReason = null;

    #[ORM\ManyToOne(inversedBy: 'bookingsAsClient')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['api:read'])]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Courier::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?Courier $courier = null;

    #[ORM\ManyToOne(targetEntity: PaymentMethod::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'booking')]
    #[Groups(['api:read'])]
    private array $messages = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        $now = new \DateTimeImmutable();
        switch ($status) {
            case 'accepted':
                $this->acceptedAt = $now;
                break;
            case 'in_progress':
                $this->pickedUpAt = $now;
                break;
            case 'delivered':
                $this->deliveredAt = $now;
                break;
            case 'cancelled':
                $this->cancelledAt = $now;
                break;
        }
        
        return $this;
    }

    public function getPickupAddress(): ?string
    {
        return $this->pickupAddress;
    }

    public function setPickupAddress(string $pickupAddress): static
    {
        $this->pickupAddress = $pickupAddress;
        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;
        return $this;
    }

    public function getPickupLatitude(): ?float
    {
        return $this->pickupLatitude;
    }

    public function setPickupLatitude(?float $pickupLatitude): static
    {
        $this->pickupLatitude = $pickupLatitude;
        return $this;
    }

    public function getPickupLongitude(): ?float
    {
        return $this->pickupLongitude;
    }

    public function setPickupLongitude(?float $pickupLongitude): static
    {
        $this->pickupLongitude = $pickupLongitude;
        return $this;
    }

    public function getDeliveryLatitude(): ?float
    {
        return $this->deliveryLatitude;
    }

    public function setDeliveryLatitude(?float $deliveryLatitude): static
    {
        $this->deliveryLatitude = $deliveryLatitude;
        return $this;
    }

    public function getDeliveryLongitude(): ?float
    {
        return $this->deliveryLongitude;
    }

    public function setDeliveryLongitude(?float $deliveryLongitude): static
    {
        $this->deliveryLongitude = $deliveryLongitude;
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

    public function getEstimatedPrice(): ?float
    {
        return $this->estimatedPrice;
    }

    public function setEstimatedPrice(?float $estimatedPrice): static
    {
        $this->estimatedPrice = $estimatedPrice;
        return $this;
    }

    public function getFinalPrice(): ?float
    {
        return $this->finalPrice;
    }

    public function setFinalPrice(?float $finalPrice): static
    {
        $this->finalPrice = $finalPrice;
        return $this;
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): static
    {
        $this->estimatedDuration = $estimatedDuration;
        return $this;
    }

    public function getActualDuration(): ?int
    {
        return $this->actualDuration;
    }

    public function setActualDuration(?int $actualDuration): static
    {
        $this->actualDuration = $actualDuration;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;
        return $this;
    }

    public function getPickedUpAt(): ?\DateTimeImmutable
    {
        return $this->pickedUpAt;
    }

    public function setPickedUpAt(?\DateTimeImmutable $pickedUpAt): static
    {
        $this->pickedUpAt = $pickedUpAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): static
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function setCancelReason(?string $cancelReason): static
    {
        $this->cancelReason = $cancelReason;
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

    public function getCourier(): ?Courier
    {
        return $this->courier;
    }

    public function setCourier(?Courier $courier): static
    {
        $this->courier = $courier;
        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    /**
     * @return array<Message>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!in_array($message, $this->messages, true)) {
            $this->messages[] = $message;
        }
        return $this;
    }

    public function removeMessage(Message $message): static
    {
        $key = array_search($message, $this->messages, true);
        if ($key !== false) {
            unset($this->messages[$key]);
        }
        return $this;
    }
}
