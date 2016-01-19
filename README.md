# PHP Rake IDF
Rapid Automatic Keyword Extraction + Inverse Document Frequency algorithm

Extracts keywords from a document that are considered more "unusual" than a standard corpus of text. Useful for extracting keywords that may represent the topic of a document.

The included idf.json file contains commonly used (mainly English language) words and their relative weightings.

## Usage
```php
$kwe = new RakeIDF;
$keywords = $kwe->run($str);
```

Uses concepts from the paper "Rose, S., D. Engel, N. Cramer, and W. Cowley (2010).Automatic keyword extraction from indi-vidual documents. In M. W. Berry and J. Kogan (Eds.), Text Mining: Applications and Theory.unknown: John Wiley and Sons, Ltd."
