<?php

declare(strict_types=1);

namespace SchababerleDigital\OData\Contract;

/**
 * Interface QueryBuilderInterface
 *
 * Definiert Methoden zum Erstellen von Client-Abfrageoptionen (Query Options).
 * Die Methoden sollten typischerweise 'self' zurückgeben, um Method Chaining zu ermöglichen.
 */
interface QueryBuilderInterface
{
    /**
     * Definiert die $select Abfrageoption.
     * Wählt eine Teilmenge der Eigenschaften aus, die zurückgegeben werden sollen.
     *
     * @param string|string[] $fields Ein einzelner Feldname, eine kommagetrennte Liste oder ein Array von Feldnamen.
     * @return self
     */
    public function select(string|array $fields): self;

    /**
     * Definiert die $filter Abfrageoption.
     * Filtert die Ergebnismenge basierend auf einem booleschen Ausdruck.
     *
     * @param string $expression Der Filterausdruck (z.B. "Name eq 'Produkt A' and Price lt 100").
     * @return self
     */
    public function filter(string $expression): self;

    /**
     * Definiert die $orderby Abfrageoption.
     * Sortiert die Ergebnismenge nach einer oder mehreren Eigenschaften.
     *
     * @param string $field Die Eigenschaft, nach der sortiert werden soll.
     * @param string $direction Die Sortierrichtung ('asc' für aufsteigend, 'desc' für absteigend).
     * @return self
     */
    public function orderBy(string $field, string $direction = 'asc'): self;

    /**
     * Definiert die $top Abfrageoption.
     * Beschränkt die Anzahl der zurückgegebenen Entitäten.
     *
     * @param int $number Die maximale Anzahl der zurückzugebenden Entitäten.
     * @return self
     */
    public function top(int $number): self;

    /**
     * Definiert die $skip Abfrageoption.
     * Überspringt eine angegebene Anzahl von Entitäten in der Ergebnismenge (für Paginierung).
     *
     * @param int $number Die Anzahl der zu überspringenden Entitäten.
     * @return self
     */
    public function skip(int $number): self;

    /**
     * Definiert die $expand Abfrageoption.
     * Schließt verknüpfte Entitäten in die Abfrageergebnisse ein.
     *
     * @param string|string[] $relations Eine einzelne Navigationseigenschaft, eine kommagetrennte Liste
     * oder ein Array von Navigationseigenschaften.
     * Verschachtelte Expands können als "Relation1/NestedRelation" angegeben werden.
     * @return self
     */
    public function expand(string|array $relations): self;

    /**
     * Definiert die $count Abfrageoption.
     * Fordert die Gesamtanzahl der Entitäten in der Ergebnismenge an (vor Anwendung von $top und $skip).
     * Das Ergebnis ist normalerweise in der Antwort als @odata.count enthalten.
     *
     * @param bool $includeCount True, um die Zählung anzufordern ($count=true), false um sie nicht anzufordern.
     * @return self
     */
    public function count(bool $includeCount = true): self;

    /**
     * Definiert die $search Abfrageoption (Client V4).
     * Ermöglicht eine Freitextsuche über die Entitäten.
     *
     * @param string $searchTerm Der Suchbegriff.
     * @return self
     */
    public function search(string $searchTerm): self;

    /**
     * Definiert die $format Abfrageoption.
     * Gibt das gewünschte Format der Antwort an (z.B. "json", "xml").
     *
     * @param string $format Das gewünschte Format (z.B. "application/json", "application/atom+xml").
     * @return self
     */
    public function format(string $format): self;

    /**
     * Fügt eine benutzerdefinierte Abfrageoption hinzu.
     *
     * @param string $paramName Der Name des Query-Parameters (ohne '$').
     * @param string $paramValue Der Wert des Query-Parameters.
     * @return self
     */
    public function custom(string $paramName, string $paramValue): self;

    /**
     * Erstellt den vollständigen Query-String-Teil der URL (beginnend mit '?').
     *
     * @return string Der zusammengesetzte Query-String oder ein leerer String, wenn keine Optionen gesetzt sind.
     */
    public function getQueryString(): string;

    /**
     * Gibt die Abfrageparameter als assoziatives Array zurück.
     * Z.B. ['$filter' => 'Name eq "Test"', '$top' => 10]
     *
     * @return array<string, string>
     */
    public function getQueryParams(): array;

    /**
     * Setzt den Entitäten-Set-Namen, auf den sich die Abfrage bezieht.
     * Dies kann für einige Operationen oder Validierungen intern nützlich sein.
     *
     * @param string $entitySet Der Name des Entitäten-Sets.
     * @return self
     */
    public function setEntitySet(string $entitySet): self;
}