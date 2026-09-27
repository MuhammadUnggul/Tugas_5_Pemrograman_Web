<?php

declare(strict_types=1);

/**
 * Merepresentasikan satu transaksi keuangan (deposit atau penarikan).
 * Properti dienkapsulasi (private) dan diisi lewat constructor property promotion.
 */
class Transaction
{
    public function __construct(
        private int $id,
        private string $type,
        private float $amount
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Memproses transaksi terhadap saldo saat ini.
     * - deposit  -> saldo bertambah
     * - withdraw -> saldo berkurang, ditolak jika saldo tidak mencukupi
     *
     * @throws RuntimeException         jika saldo tidak mencukupi untuk penarikan
     * @throws InvalidArgumentException jika jenis transaksi tidak dikenal
     */
    public function process(float $currentBalance): float
    {
        return match ($this->type) {
            'deposit'  => $currentBalance + $this->amount,
            'withdraw' => $this->processWithdraw($currentBalance),
            default    => throw new InvalidArgumentException('Jenis transaksi tidak dikenal.'),
        };
    }

    private function processWithdraw(float $currentBalance): float
    {
        if ($this->amount > $currentBalance) {
            throw new RuntimeException('Saldo tidak mencukupi untuk melakukan penarikan.');
        }

        return $currentBalance - $this->amount;
    }

    /**
     * Representasi array untuk disimpan di sesi / ditampilkan.
     */
    public function toArray(): array
    {
        return [
            'id'     => $this->id,
            'type'   => $this->type,
            'amount' => $this->amount,
        ];
    }
}
