<?php

namespace App\Services;

/**
 * Expose l'état de distribution de l'application mobile CLANDO : APK en
 * téléchargement direct tant que la fiche Google Play n'est pas validée,
 * puis bascule automatique vers les stores une fois les URLs renseignées.
 */
class MobileAppService
{
    public function apkPath(): string
    {
        return config('mobile_app.android.apk_path');
    }

    public function apkIsAvailable(): bool
    {
        return is_file($this->apkPath()) && is_readable($this->apkPath());
    }

    public function apkSize(): ?int
    {
        if (! $this->apkIsAvailable()) {
            return null;
        }

        $size = @filesize($this->apkPath());

        return $size === false ? null : $size;
    }

    /**
     * Taille lisible par un humain, ex. « 24,3 Mo ».
     */
    public function apkSizeLabel(): ?string
    {
        $size = $this->apkSize();

        if ($size === null) {
            return null;
        }

        return number_format($size / 1024 / 1024, 1, ',', ' ') . ' Mo';
    }

    /**
     * Date de mise en ligne de l'APK courant, ex. « 01/08/2026 ».
     */
    public function apkUpdatedAt(): ?string
    {
        if (! $this->apkIsAvailable()) {
            return null;
        }

        $timestamp = @filemtime($this->apkPath());

        return $timestamp === false ? null : date('d/m/Y', $timestamp);
    }

    /* ------------------------------------------------------------------ */
    /*  Application agent                                                   */
    /* ------------------------------------------------------------------ */

    public function agentApkPath(): string
    {
        return config('mobile_app.agent.apk_path');
    }

    public function agentApkIsAvailable(): bool
    {
        return is_file($this->agentApkPath()) && is_readable($this->agentApkPath());
    }

    public function agentApkSizeLabel(): ?string
    {
        if (! $this->agentApkIsAvailable()) {
            return null;
        }

        $taille = @filesize($this->agentApkPath());

        return $taille === false ? null : number_format($taille / 1024 / 1024, 1, ',', ' ') . ' Mo';
    }

    public function agentApkUpdatedAt(): ?string
    {
        if (! $this->agentApkIsAvailable()) {
            return null;
        }

        $horodatage = @filemtime($this->agentApkPath());

        return $horodatage === false ? null : date('d/m/Y', $horodatage);
    }

    /**
     * Ce que l'écran Agents affiche pour partager l'application.
     *
     * @return array<string, mixed>
     */
    public function agent(): array
    {
        return [
            'name' => config('mobile_app.agent.name'),
            'apk_available' => $this->agentApkIsAvailable(),
            'apk_url' => route('app.agent.apk'),
            'apk_size' => $this->agentApkSizeLabel(),
            'apk_updated_at' => $this->agentApkUpdatedAt(),
            'version' => config('mobile_app.agent.version'),
            'min_os' => config('mobile_app.agent.min_os'),
            // Chemin attendu sur le serveur, affiché à l'administration quand le
            // fichier manque : sans cette indication, « indisponible » ne dit
            // pas quoi faire pour y remédier.
            'apk_path' => $this->agentApkPath(),
        ];
    }

    public function playStoreUrl(): ?string
    {
        return config('mobile_app.android.play_store_url') ?: null;
    }

    public function appStoreUrl(): ?string
    {
        return config('mobile_app.ios.app_store_url') ?: null;
    }

    /**
     * Props transmises au front pour construire le bloc de présentation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => config('mobile_app.name'),
            'android' => [
                'apk_available' => $this->apkIsAvailable(),
                'apk_url' => route('shop.app.android.apk'),
                'apk_size' => $this->apkSizeLabel(),
                'apk_updated_at' => $this->apkUpdatedAt(),
                'version' => config('mobile_app.android.version'),
                'min_os' => config('mobile_app.android.min_os'),
                'play_store_url' => $this->playStoreUrl(),
            ],
            'ios' => [
                'app_store_url' => $this->appStoreUrl(),
            ],
        ];
    }
}
