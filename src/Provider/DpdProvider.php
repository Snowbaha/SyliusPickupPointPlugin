<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Provider;

use function Safe\preg_replace;
use Answear\DpdPlPickupServicesBundle\ValueObject\WeekStoreHours;
use Answear\DpdPlPickupServicesBundle\Service\PUDOList;
use Answear\DpdPlPickupServicesBundle\ValueObject\PUDO;
use Answear\DpdPlPickupServicesBundle\Exception\ServiceException;
use GuzzleHttp\ClientInterface;
use Setono\SyliusPickupPointPlugin\Exception\TimeoutException;
use Setono\SyliusPickupPointPlugin\Model\PickupPoint;
use Setono\SyliusPickupPointPlugin\Model\PickupPointCode;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Sylius\Component\Core\Model\OrderInterface;


final class DpdProvider extends Provider
{
    const DEFAULT_COUNTRY = 'FR'; // Country by default if empty  @todo let configure it in the .yml ?

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
            $pudo = $this->PUDOList->byIdFr($code->getIdPart());

            return $this->transform($pudo);
        } catch (ServiceException  $e) {
            return null;
        }
    }

    /**
     * Not possible with DPD
     */
    public function findAllPickupPoints(): iterable
    {
        return [];
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
        $country = "" !== $pudo->address->country ? $pudo->address->country : self::DEFAULT_COUNTRY;
        $pickupPoint = new PickupPoint(
            new PickupPointCode($pudo->id, $this->getCode(), $country),
            $pudo->name,
            $pudo->address->getFullAddress(), // @todo add the locationHint ?
            $pudo->address->zipCode,
            $pudo->address->city,
            $country, // getCountryCode
            (string)$pudo->coordinates->latitude,
            (string)$pudo->coordinates->longitude,
            $pudo->distance ?? null
        );
        $pickupPoint->opened = $this->transformOpeningHours($pudo->opened, $country);

        return $pickupPoint;
    }

    /**
     * transform the brut data into something readable.
     */
    private function transformOpeningHours(WeekStoreHours $opened, string $country): array
    {
        $days_value = [];

        if('FR' === $country){
            $days_label = array('Lundi', 'Mardi', 'Mercredi','Jeudi','Vendredi', 'Samedi', 'Dimanche');
        }else{
            $days_label = array('Monday', 'Tuesday', 'Wednesday','Thursday','Friday', 'Saturday', 'Sunday');
        }

        foreach ($opened->getIterator() as $hours){

            foreach ($hours as $h){
                $day = $h->day->getValue() - 1; //days start 1 but we need 0 for the index

                if(!isset($days[$day])){
                    $days_value[$day] = ($days[$day] ?? null).' '.sprintf(
                            '%s : %s  - %s',
                            $days_label[$day],
                            $h->from,
                            $h->to
                        );
                }else{
                    $days_value[$day] .= sprintf(
                        ' | %s - %s',
                        $h->from,
                        $h->to
                    );
                }


            }
        }

        return $days_value;
    }
}
