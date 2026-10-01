<style>
    body { font-family: dejavusans; font-size: 9pt; color: #1b1d21; line-height: 1.35; }
    h1 { color: {{ $couleur }}; font-size: 20pt; margin: 0; }
    h2 { color: {{ $couleur }}; font-size: 11pt; margin: 10px 0 4px; }
    table { border-collapse: collapse; width: 100%; }
    .entete td { vertical-align: top; }
    .societe { font-size: 8.5pt; }
    .societe strong { font-size: 11pt; color: {{ $couleur }}; }
    .cadre { border: 0.3mm solid #bbb; padding: 7px; }
    .client { border-left: 1.2mm solid {{ $couleur }}; padding: 6px 8px; background: #f4f5f7; }
    .infos td { padding: 2px 0; font-size: 8.5pt; }
    .lignes { margin-top: 8px; }
    .lignes th { background: {{ $couleur }}; color: #fff; padding: 5px; text-align: left; font-size: 8pt; }
    .lignes td { padding: 5px; border-bottom: 0.2mm solid #ddd; vertical-align: top; }
    .lignes .section td { background: #eef1f5; font-weight: bold; border-bottom: 0.3mm solid {{ $couleur }}; }
    .lignes .texte td { color: #555; font-style: italic; }
    .detail { color: #555; font-size: 7.8pt; }
    .droite { text-align: right; }
    .totaux td { padding: 3px 6px; }
    .total { font-weight: bold; font-size: 11pt; color: {{ $couleur }}; border-top: 0.4mm solid {{ $couleur }}; }
    .mentions { font-size: 7.6pt; color: #333; }
    .mentions p { margin: 3px 0; }
    .encadre { border: 0.3mm solid {{ $couleur }}; padding: 6px; margin-top: 6px; }
    .petit { font-size: 7.5pt; color: #555; }
    .option { color: #555; }
</style>
