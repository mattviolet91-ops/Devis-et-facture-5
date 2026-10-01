<?php

namespace App\Http\Requests;

use App\Models\Client;
use App\Rules\Siret;
use App\Rules\TvaIntracom;
use App\Support\Telephone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'siret' => $this->siret ? Siret::nettoyer((string) $this->siret) : null,
            'tva_intracom' => $this->tva_intracom ? TvaIntracom::nettoyer((string) $this->tva_intracom) : null,
            'telephone_normalise' => Telephone::normaliser($this->telephone),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Client::PARTICULIER, Client::PROFESSIONNEL])],
            'civilite' => ['nullable', Rule::in(Client::CIVILITES)],
            'nom' => ['required_if:type,particulier', 'nullable', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'raison_sociale' => ['required_if:type,professionnel', 'nullable', 'string', 'max:150'],
            'siret' => ['nullable', new Siret],
            'tva_intracom' => ['nullable', new TvaIntracom($this->siret)],
            'telephone' => ['required_without:email', 'nullable', 'string', 'max:25', 'regex:/^[0-9 +().\-]{6,25}$/'],
            'telephone2' => ['nullable', 'string', 'max:25', 'regex:/^[0-9 +().\-]{6,25}$/'],
            'email' => ['required_without:telephone', 'nullable', 'email', 'max:150'],
            'adresse' => ['nullable', 'string', 'max:200'],
            'code_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'ville' => ['nullable', 'string', 'max:100'],
            'provenance' => ['nullable', 'string', 'max:60'],
            'provenance_detail' => ['nullable', 'string', 'max:150'],
            'confirmer_doublon' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required_if' => 'Indiquez le nom du client.',
            'raison_sociale.required_if' => 'Indiquez le nom de la société.',
            'telephone.required_without' => 'Indiquez au moins un téléphone ou un email.',
            'email.required_without' => 'Indiquez au moins un téléphone ou un email.',
            'telephone.regex' => 'Ce numéro de téléphone n\'est pas valable.',
            'telephone2.regex' => 'Ce numéro de téléphone n\'est pas valable.',
            'code_postal.regex' => 'Le code postal fait 5 chiffres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'raison_sociale' => 'société', 'telephone' => 'téléphone', 'telephone2' => 'autre téléphone',
            'code_postal' => 'code postal', 'tva_intracom' => 'n° de TVA',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function donnees(): array
    {
        $donnees = $this->safe()->except(['confirmer_doublon']);

        if (($donnees['type'] ?? null) === Client::PARTICULIER) {
            $donnees['raison_sociale'] = null;
            $donnees['siret'] = null;
            $donnees['tva_intracom'] = null;
        }

        return $donnees;
    }
}
