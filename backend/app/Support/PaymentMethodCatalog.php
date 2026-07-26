<?php

namespace App\Support;

final class PaymentMethodCatalog
{
    public const QRIS = 'qris';

    public const CASH = 'cash';

    public const LEGACY_TRANSFER = 'transfer';

    /** @var array<string, string> */
    public const VIRTUAL_ACCOUNTS = [
        'bri_va' => 'Virtual Account BRI',
        'bni_va' => 'Virtual Account BNI',
        'cimb_niaga_va' => 'Virtual Account CIMB Niaga',
        'permata_va' => 'Virtual Account Permata',
        'atm_bersama_va' => 'Virtual Account ATM Bersama',
        'bnc_va' => 'Virtual Account Bank Neo Commerce',
        'maybank_va' => 'Virtual Account Maybank',
        'artha_graha_va' => 'Virtual Account Artha Graha',
        'sampoerna_va' => 'Virtual Account Bank Sampoerna',
    ];

    /** Daftar metode yang boleh dipilih untuk pembayaran baru. */
    public static function selectableCodes(): array
    {
        return [self::QRIS, ...array_keys(self::VIRTUAL_ACCOUNTS), self::CASH];
    }

    /** Daftar metode digital yang dibuat melalui Direct API Pakasir. */
    public static function directGatewayCodes(): array
    {
        return [self::QRIS, ...array_keys(self::VIRTUAL_ACCOUNTS)];
    }

    /** Metode gateway baru dan transfer lama yang masih perlu dibaca. */
    public static function handledGatewayCodes(): array
    {
        return [...self::directGatewayCodes(), self::LEGACY_TRANSFER];
    }

    public static function isVirtualAccount(string $code): bool
    {
        return array_key_exists($code, self::VIRTUAL_ACCOUNTS);
    }

    public static function isDirectGateway(string $code): bool
    {
        return in_array($code, self::directGatewayCodes(), true);
    }

    public static function isHandledGateway(string $code): bool
    {
        return in_array($code, self::handledGatewayCodes(), true);
    }

    public static function family(string $code): string
    {
        return match (true) {
            $code === self::CASH => 'cash',
            $code === self::QRIS => 'qris',
            self::isVirtualAccount($code), $code === self::LEGACY_TRANSFER => 'va',
            default => 'unknown',
        };
    }

    public static function label(string $code): string
    {
        return match ($code) {
            self::QRIS => 'QRIS',
            self::CASH => 'Bayar Tunai',
            self::LEGACY_TRANSFER => 'Transfer Bank (Legacy)',
            default => self::VIRTUAL_ACCOUNTS[$code]
                ?? strtoupper(str_replace('_', ' ', $code)),
        };
    }

    /**
     * @return array<int, array{code: string, label: string, family: string}>
     */
    public static function selectableOptions(
        bool $qrisEnabled,
        bool $virtualAccountEnabled,
        bool $cashEnabled,
    ): array {
        $codes = [];

        if ($qrisEnabled) {
            $codes[] = self::QRIS;
        }
        if ($virtualAccountEnabled) {
            array_push($codes, ...array_keys(self::VIRTUAL_ACCOUNTS));
        }
        if ($cashEnabled) {
            $codes[] = self::CASH;
        }

        return array_map(fn (string $code) => [
            'code' => $code,
            'label' => self::label($code),
            'family' => self::family($code),
        ], $codes);
    }

    /** @return array<string, string> */
    public static function adminOptions(): array
    {
        $codes = [self::LEGACY_TRANSFER, ...self::selectableCodes()];

        return array_combine(
            $codes,
            array_map([self::class, 'label'], $codes),
        );
    }

    /** Mencocokkan metode pilihan pengguna dengan channel dari Pakasir. */
    public static function gatewayChannelMatches(string $selectedMethod, string $reportedMethod): bool
    {
        if ($selectedMethod === self::LEGACY_TRANSFER) {
            return self::isVirtualAccount($reportedMethod)
                || $reportedMethod === self::LEGACY_TRANSFER;
        }

        return $selectedMethod === $reportedMethod;
    }
}
