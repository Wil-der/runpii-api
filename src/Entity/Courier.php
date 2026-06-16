<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
class Courier extends User
{
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $vehicleType = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?string $vehicleLicensePlate = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?bool $isAvailable = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['api:read', 'api:write'])]
    private ?array $currentLocation = null;

    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'courier')]
    private Collection $bookings;

    public function __construct()
    {
        parent::__construct();
        $this->setRoles(['ROLE_COURIER']);
        $this->isAvailable = true;
        $this->bookings = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getVehicleType(): ?string
    {
        return $this->vehicleType;
    }

    public function setVehicleType(?string $vehicleType): static
    {
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getVehicleLicensePlate(): ?string
    {
        return $this->vehicleLicensePlate;
    }

    public function setVehicleLicensePlate(?string $vehicleLicensePlate): static
    {
        $this->vehicleLicensePlate = $vehicleLicensePlate;
        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(?bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getCurrentLocation(): ?array
    {
        return $this->currentLocation;
    }

    public function setCurrentLocation(?array $currentLocation): static
    {
        $this->currentLocation = $currentLocation;
        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setCourier($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getCourier() === $this) {
                $booking->setCourier(null);
            }
        }
        return $this;
    }
}
