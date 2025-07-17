<?php

declare(strict_types=1);

namespace LinkShortener\Services;

use LinkShortener\Repositories\LinkRepository;
use LinkShortener\Config\Cache;
use Random\RandomException;

class ShortCodeGeneratorService
{
    private LinkRepository $linkRepository;
    private string $alphabet;
    private int $length;
    private int $maxAttempts;

    public function __construct(LinkRepository $linkRepository)
    {
        $this->linkRepository = $linkRepository;
        $this->alphabet = $_ENV['SHORT_CODE_ALPHABET'] ?? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $this->length = (int)($_ENV['SHORT_CODE_LENGTH'] ?? 6);
        $this->maxAttempts = 100;
    }

    public function generateUniqueShortCode(): string
    {
        $attempts = 0;
        
        while ($attempts < $this->maxAttempts) {
            $shortCode = $this->generateShortCode();
            
            // Check cache first for faster lookups
            $cacheKey = 'short_code_exists:' . $shortCode;
            $cached = Cache::get($cacheKey);
            
            if ($cached === null) {
                // Check database
                $exists = $this->linkRepository->shortCodeExists($shortCode);
                // Cache the result for 1 hour
                Cache::set($cacheKey, $exists ? '1' : '0', 3600);
                
                if (!$exists) {
                    return $shortCode;
                }
            } elseif ($cached === '0') {
                // Not in cache and doesn't exist in DB
                return $shortCode;
            }
            
            $attempts++;
        }
        
        throw new \RuntimeException('Unable to generate unique short code after ' . $this->maxAttempts . ' attempts');
    }

    private function generateShortCode(): string
    {
        $shortCode = '';
        $alphabetLength = strlen($this->alphabet);
        
        try {
            for ($i = 0; $i < $this->length; $i++) {
                $shortCode .= $this->alphabet[random_int(0, $alphabetLength - 1)];
            }
        } catch (RandomException $e) {
            // Fallback to mt_rand if random_int fails
            for ($i = 0; $i < $this->length; $i++) {
                $shortCode .= $this->alphabet[mt_rand(0, $alphabetLength - 1)];
            }
        }
        
        return $shortCode;
    }

    public function generateCustomShortCode(string $customCode): string
    {
        // Validate custom code
        if (!$this->isValidCustomCode($customCode)) {
            throw new \InvalidArgumentException('Invalid custom short code');
        }
        
        // Check if it already exists
        if ($this->linkRepository->shortCodeExists($customCode)) {
            throw new \InvalidArgumentException('Custom short code already exists');
        }
        
        return $customCode;
    }

    private function isValidCustomCode(string $code): bool
    {
        // Check length
        if (strlen($code) < 3 || strlen($code) > 20) {
            return false;
        }
        
        // Check if all characters are in the allowed alphabet
        for ($i = 0; $i < strlen($code); $i++) {
            if (strpos($this->alphabet, $code[$i]) === false) {
                return false;
            }
        }
        
        // Check for reserved words
        $reservedWords = ['api', 'admin', 'www', 'mail', 'ftp', 'stats', 'help', 'about', 'contact'];
        if (in_array(strtolower($code), $reservedWords)) {
            return false;
        }
        
        return true;
    }

    public function generateBulkShortCodes(int $count): array
    {
        $shortCodes = [];
        $attempts = 0;
        $maxTotalAttempts = $count * 10;
        
        while (count($shortCodes) < $count && $attempts < $maxTotalAttempts) {
            try {
                $shortCode = $this->generateUniqueShortCode();
                $shortCodes[] = $shortCode;
            } catch (\RuntimeException $e) {
                // If we can't generate a unique code, try again
                $attempts++;
                continue;
            }
        }
        
        if (count($shortCodes) < $count) {
            throw new \RuntimeException('Unable to generate ' . $count . ' unique short codes');
        }
        
        return $shortCodes;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getTotalPossibleCombinations(): int
    {
        return pow(strlen($this->alphabet), $this->length);
    }

    public function getCollisionProbability(int $existingCodes): float
    {
        $totalCombinations = $this->getTotalPossibleCombinations();
        return $existingCodes / $totalCombinations;
    }

    public function suggestOptimalLength(int $expectedLinks): int
    {
        $alphabetSize = strlen($this->alphabet);
        $targetCollisionRate = 0.01; // 1% collision rate
        
        $optimalLength = ceil(log($expectedLinks / $targetCollisionRate) / log($alphabetSize));
        
        return max(3, min(20, (int)$optimalLength));
    }

    public function analyzeEntropy(): array
    {
        $alphabetSize = strlen($this->alphabet);
        $totalCombinations = $this->getTotalPossibleCombinations();
        $entropyBits = log($totalCombinations, 2);
        
        return [
            'alphabet_size' => $alphabetSize,
            'code_length' => $this->length,
            'total_combinations' => $totalCombinations,
            'entropy_bits' => $entropyBits,
            'estimated_uniqueness_years' => $this->estimateUniquenessYears()
        ];
    }

    private function estimateUniquenessYears(): float
    {
        // Assume 1 million links per year (adjust based on usage)
        $linksPerYear = 1000000;
        $totalCombinations = $this->getTotalPossibleCombinations();
        
        // Use birthday paradox approximation
        $years = sqrt(pi() * $totalCombinations / 2) / $linksPerYear;
        
        return round($years, 2);
    }

    public function validateShortCode(string $shortCode): bool
    {
        // Check length
        if (strlen($shortCode) !== $this->length) {
            return false;
        }
        
        // Check if all characters are in the allowed alphabet
        for ($i = 0; $i < strlen($shortCode); $i++) {
            if (strpos($this->alphabet, $shortCode[$i]) === false) {
                return false;
            }
        }
        
        return true;
    }
}