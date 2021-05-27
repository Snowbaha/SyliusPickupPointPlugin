<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Provider;

use function Safe\preg_replace;
use Answear\DpdPlPickupServicesBundle\Service\PUDOList;
use Answear\DpdPlPickupServicesBundle\ValueObject\PUDO;
use GuzzleHttp\ClientInterface;



use Setono\SyliusPickupPointPlugin\Exception\TimeoutException;
use Setono\SyliusPickupPointPlugin\Model\PickupPoint;
use Setono\SyliusPickupPointPlugin\Model\PickupPointCode;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Sylius\Component\Core\Model\OrderInterface;


final class DpdProvider extends Provider
{
    /** @var PUDOList */
    private $PUDOList;


    public function __construct(PUDOList $PUDOList)
    {
        $this->PUDOList = $PUDOList;
    }

    public function findPickupPoints(OrderInterface $order): iterable
    {
        $shippingAddress = $order->getShippingAddress();
        if (null === $shippingAddress) {
            return [];
        }

        $street = $shippingAddress->getStreet();
        $postCode = $shippingAddress->getPostcode();
        $countryCode = $shippingAddress->getCountryCode();
        $city = $shippingAddress->getCity();
        if (null === $street || null === $postCode || null === $countryCode) {
            return [];
        }

        try {
            $pudos = $this->PUDOList->byAddressFr(preg_replace('/\s+/', '', $postCode), $city, $street);
        } catch (\Exception $e) {
            throw new TimeoutException($e);
        }

        $pickupPoints = [];
        foreach ($pudos as $item) {
            $pickupPoints[] = $this->transform($item);
        }

        return $pickupPoints;
    }

    public function findPickupPoint(PickupPointCode $code): ?PickupPointInterface
    {
        try {
            $parcelShop = $this->client->getOneParcelShop($code->getIdPart());

            return $this->transform($parcelShop);
        } catch (ParcelShopNotFoundException $e) {
            return null;
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        }
    }

    public function findAllPickupPoints(): iterable
    {
        try {
            foreach ($this->countryCodes as $countryCode) {
                $parcelShops = $this->client->getAllParcelShops($countryCode);

                foreach ($parcelShops as $item) {
                    yield $this->transform($item);
                }
            }
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        } catch (NoResultException $e) {
            return [];
        }
    }

    public function getCode(): string
    {
        return 'dpd';
    }

    public function getName(): string
    {
        return 'DPD';
    }

    private function transform(PUDO $pudo): PickupPoint
    {
        $country = $pudo->address->country ? $pudo->address->country : 'FR'; // @todo manger other country issue

        return new PickupPoint(
            new PickupPointCode($pudo->id, $this->getCode(), $country),
            $pudo->name,
            $pudo->address->address1, // @todo use the full address2, address3, locationHint
            $pudo->address->zipCode,
            $pudo->address->city,
            $country, // getCountryCode
            (string)$pudo->coordinates->latitude,
            (string)$pudo->coordinates->longitude,
            $pudo->distance ?? null
        );
    }
}
