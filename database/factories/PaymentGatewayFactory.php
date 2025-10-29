<?php

namespace Database\Factories;

use App\Exceptions\PaymentException;
use App\Gateways\FlutterwaveGateway;
use App\Gateways\PaystackGateway;
use App\Interfaces\Payable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentGateway>
 */
class PaymentGatewayFactory extends Factory
{
    private static array $gateways = [
        'paystack' => PaystackGateway::class,
        'flutterwave' => FlutterwaveGateway::class,
    ];

    /**
     * Create a gateway instance
     *
     * @param string $gatewayName
     * @return Payable
     * @throws PaymentException
     */
    public static function createGateway(string $gatewayName): Payable {
        $gatewayName = strtolower(trim($gatewayName));

        if (!isset(self::$gateways[$gatewayName])) {
            throw new PaymentException(
                "Unsupported payment gateway: {$gatewayName}",
                400
            );
        }

        $gatewayClass = self::$gateways[$gatewayName];
        return new $gatewayClass();
    }

    /**
     * Register a new gateway
     *
     * @param string $name
     * @param string $class
     */
    public static function register(string $name, string $class): void {
        self::$gateways[strtolower($name)] = $class;
    }
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $code = fake()->randomElement(['paystack', 'flutterwave']),
            'name' => $name = ucwords($code),
            'description' => $name.' payment gateway',
            'is_default' => false,
        ];
    }
}
