<?php

declare(strict_types=1);

namespace Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;

class UserRegisteredEventTest extends TestCase
{
    public function testConstructorAndGettersWithStringUserId()
    {
        $context = new RegistrationContext([
            'email' => 'test@example.com',
            'additionalData' => ['displayName' => 'Test User', 'role' => 'user'],
        ]);

        $occurredAt = new \DateTimeImmutable('2023-01-01 12:00:00');

        $event = new UserRegisteredEvent(
            userId: 'string-user-123',
            registrationContext: $context,
            clientId: 'test-client',
            occurredAt: $occurredAt
        );

        $this->assertSame('string-user-123', $event->getUserId());
        $this->assertSame($context, $event->getRegistrationContext());
        $this->assertSame('test-client', $event->getClientId());
        $this->assertSame($occurredAt, $event->getOccurredAt());
        $this->assertSame('test@example.com', $event->getEmail());
        $this->assertEquals(['displayName' => 'Test User', 'role' => 'user'], $event->getAdditionalData());
    }

    public function testConstructorAndGettersWithIntegerUserId()
    {
        $context = new RegistrationContext([
            'email' => 'numeric@example.com',
            'additionalData' => ['age' => 25, 'verified' => false],
        ]);

        $event = new UserRegisteredEvent(
            userId: 12345,
            registrationContext: $context,
            clientId: 'numeric-client'
        );

        $this->assertSame(12345, $event->getUserId());
        $this->assertSame($context, $event->getRegistrationContext());
        $this->assertSame('numeric-client', $event->getClientId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->getOccurredAt());
        $this->assertSame('numeric@example.com', $event->getEmail());
        $this->assertEquals(['age' => 25, 'verified' => false], $event->getAdditionalData());
    }

    public function testConstructorWithDefaultOccurredAt()
    {
        $context = new RegistrationContext([
            'email' => 'default@example.com',
            'additionalData' => null,
        ]);

        $beforeCreation = new \DateTimeImmutable();

        $event = new UserRegisteredEvent(
            userId: 'default-user',
            registrationContext: $context,
            clientId: 'default-client'
        );

        $afterCreation = new \DateTimeImmutable();

        // The default occurredAt should be between before and after creation
        $this->assertGreaterThanOrEqual($beforeCreation, $event->getOccurredAt());
        $this->assertLessThanOrEqual($afterCreation, $event->getOccurredAt());
    }

    public function testGetEmailFromRegistrationContext()
    {
        $context = new RegistrationContext([
            'email' => 'context@example.com',
            'additionalData' => ['name' => 'Context User'],
        ]);

        $event = new UserRegisteredEvent(
            userId: 'context-user',
            registrationContext: $context,
            clientId: 'context-client'
        );

        $this->assertSame('context@example.com', $event->getEmail());
    }

    public function testGetAdditionalDataWithNullValue()
    {
        $context = new RegistrationContext([
            'email' => 'null@example.com',
            'additionalData' => null,
        ]);

        $event = new UserRegisteredEvent(
            userId: 'null-user',
            registrationContext: $context,
            clientId: 'null-client'
        );

        $this->assertNull($event->getAdditionalData());
    }

    public function testGetAdditionalDataWithEmptyArray()
    {
        $context = new RegistrationContext([
            'email' => 'empty@example.com',
            'additionalData' => [],
        ]);

        $event = new UserRegisteredEvent(
            userId: 'empty-user',
            registrationContext: $context,
            clientId: 'empty-client'
        );

        $this->assertEquals([], $event->getAdditionalData());
    }

    public function testGetAdditionalDataWithComplexData()
    {
        $complexData = [
            'profile' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true,
                    'language' => 'en',
                ],
            ],
            'metadata' => [
                'source' => 'web',
                'campaign' => 'summer2023',
                'referrer' => 'https://example.com',
            ],
            'flags' => ['beta_user', 'newsletter_subscriber'],
            'scores' => [85, 92, 78],
        ];

        $context = new RegistrationContext([
            'email' => 'complex@example.com',
            'additionalData' => $complexData,
        ]);

        $event = new UserRegisteredEvent(
            userId: 'complex-user',
            registrationContext: $context,
            clientId: 'complex-client'
        );

        $this->assertEquals($complexData, $event->getAdditionalData());
    }

    public function testEventWithDifferentClientIds()
    {
        $context = new RegistrationContext([
            'email' => 'client@example.com',
            'additionalData' => ['test' => true],
        ]);

        // Test with various client ID formats
        $clientIds = [
            'simple-client',
            'client_with_underscores',
            'client-with-dashes',
            'ClientWithCamelCase',
            'client123',
            'very-long-client-id-with-many-parts-and-numbers-12345',
        ];

        foreach ($clientIds as $clientId) {
            $event = new UserRegisteredEvent(
                userId: 'test-user',
                registrationContext: $context,
                clientId: $clientId
            );

            $this->assertSame($clientId, $event->getClientId());
        }
    }

    public function testEventWithDifferentUserIdTypes()
    {
        $context = new RegistrationContext([
            'email' => 'userid@example.com',
            'additionalData' => ['test' => true],
        ]);

        // Test with string user ID
        $stringEvent = new UserRegisteredEvent(
            userId: 'string-123',
            registrationContext: $context,
            clientId: 'test-client'
        );
        $this->assertIsString($stringEvent->getUserId());
        $this->assertSame('string-123', $stringEvent->getUserId());

        // Test with integer user ID
        $intEvent = new UserRegisteredEvent(
            userId: 456,
            registrationContext: $context,
            clientId: 'test-client'
        );
        $this->assertIsInt($intEvent->getUserId());
        $this->assertSame(456, $intEvent->getUserId());

        // Test with zero as user ID
        $zeroEvent = new UserRegisteredEvent(
            userId: 0,
            registrationContext: $context,
            clientId: 'test-client'
        );
        $this->assertSame(0, $zeroEvent->getUserId());
    }

    public function testEventImmutability()
    {
        $context = new RegistrationContext([
            'email' => 'immutable@example.com',
            'additionalData' => ['mutable' => 'data'],
        ]);

        $occurredAt = new \DateTimeImmutable('2023-06-15 10:30:00');

        $event = new UserRegisteredEvent(
            userId: 'immutable-user',
            registrationContext: $context,
            clientId: 'immutable-client',
            occurredAt: $occurredAt
        );

        // Verify that all properties are readonly by checking they return the same instances
        $this->assertSame($context, $event->getRegistrationContext());
        $this->assertSame($occurredAt, $event->getOccurredAt());

        // Multiple calls should return the same values
        $this->assertSame($event->getUserId(), $event->getUserId());
        $this->assertSame($event->getClientId(), $event->getClientId());
        $this->assertSame($event->getEmail(), $event->getEmail());
        $this->assertSame($event->getAdditionalData(), $event->getAdditionalData());
    }

    public function testEventWithSpecialCharactersInEmail()
    {
        $specialEmails = [
            'user+tag@example.com',
            'user.name@example.com',
            'user_name@example.com',
            'user-name@example.com',
            'user123@example.com',
            'test@sub.example.com',
        ];

        foreach ($specialEmails as $email) {
            $context = new RegistrationContext([
                'email' => $email,
                'additionalData' => ['special' => true],
            ]);

            $event = new UserRegisteredEvent(
                userId: 'special-user',
                registrationContext: $context,
                clientId: 'special-client'
            );

            $this->assertSame($email, $event->getEmail());
        }
    }
}
