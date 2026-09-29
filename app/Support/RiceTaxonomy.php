<?php

namespace App\Support;

/**
 * Hard-coded, per-dialect display data for the 23 canonical classes, so Groq
 * never spends tokens translating class names, severity labels, or severity
 * messages.
 *
 * SOURCING / VERIFICATION
 * - Names marked verified in $tagalogVerified follow the Filipino names used
 *   in PhilRice's "Harmful organisms in Philippine rice fields" and its
 *   "Pesteng Insekto at Sakit Labanan" flyer (atangya, aksip, magbibilot /
 *   maniniklop, ngusong kabayo, langaw palay, golden kuhol, tungro, BLB).
 * - Cebuano and Hiligaynon: no published PhilRice/IRRI list was found, so
 *   these use the standard technical name farmers/technicians already say
 *   (BPH, Tungro, Leaf Folder ...) and only use a local word where it is
 *   well known (Himsog, kuhol, atangya). They are NOT officially verified —
 *   have a PhilRice regional office / local agriculturist confirm them, then
 *   edit the JSON below. Nothing else in the app needs to change.
 * - Severity labels/messages in all three non-English dialects are
 *   translations of the app's existing English text, not from a published
 *   source.
 */
class RiceTaxonomy
{
    /** Classes whose Tagalog name is taken from PhilRice material. */
    public static array $tagalogVerified = ['bacterial_leaf_blight', 'tungro_virus', 'applesnail_eggs', 'brown_planthopper', 'dead_heart', 'green_leafhopper', 'leaf_folders', 'rice_bug', 'rice_leaf_roller', 'rice_stem_borer', 'whorl_maggot'];

    public static function payload(): array
    {
        static $cache = null;
        return $cache ??= json_decode(self::JSON, true) ?: [];
    }

    public static function name(string $key, string $language = 'english'): ?string
    {
        return self::payload()['names'][$key][$language] ?? null;
    }

    private const JSON = <<<'JSON'
{
 "names": {
  "healthy_rice_plant": {
   "english": "Healthy Rice Plant",
   "tagalog": "Malusog na Palay",
   "cebuano": "Himsog nga Humay",
   "hiligaynon": "Himsog nga Humay"
  },
  "bacterial_leaf_blight": {
   "english": "Bacterial Leaf Blight",
   "tagalog": "Bacterial Leaf Blight (BLB)",
   "cebuano": "Bacterial Leaf Blight (BLB)",
   "hiligaynon": "Bacterial Leaf Blight (BLB)"
  },
  "bacterial_leaf_streak": {
   "english": "Bacterial Leaf Streak",
   "tagalog": "Bacterial Leaf Streak",
   "cebuano": "Bacterial Leaf Streak",
   "hiligaynon": "Bacterial Leaf Streak"
  },
  "brown_spot": {
   "english": "Brown Spot",
   "tagalog": "Brown Spot",
   "cebuano": "Brown Spot",
   "hiligaynon": "Brown Spot"
  },
  "downy_mildew": {
   "english": "Downy Mildew",
   "tagalog": "Downy Mildew",
   "cebuano": "Downy Mildew",
   "hiligaynon": "Downy Mildew"
  },
  "leaf_blast": {
   "english": "Leaf Blast",
   "tagalog": "Blast (Leaf Blast)",
   "cebuano": "Blast (Leaf Blast)",
   "hiligaynon": "Blast (Leaf Blast)"
  },
  "rice_false_smut": {
   "english": "Rice False Smut",
   "tagalog": "False Smut",
   "cebuano": "False Smut",
   "hiligaynon": "False Smut"
  },
  "sheath_blight": {
   "english": "Sheath Blight",
   "tagalog": "Sheath Blight",
   "cebuano": "Sheath Blight",
   "hiligaynon": "Sheath Blight"
  },
  "tungro_virus": {
   "english": "Tungro Virus",
   "tagalog": "Tungro",
   "cebuano": "Tungro",
   "hiligaynon": "Tungro"
  },
  "applesnail_eggs": {
   "english": "Apple Snail Eggs",
   "tagalog": "Itlog ng Golden Kuhol",
   "cebuano": "Itlog sa Golden Kuhol",
   "hiligaynon": "Itlog sang Golden Kuhol"
  },
  "brown_planthopper": {
   "english": "Brown Planthopper",
   "tagalog": "Kayumangging Ngusong Kabayo (BPH)",
   "cebuano": "Brown Planthopper (BPH)",
   "hiligaynon": "Brown Planthopper (BPH)"
  },
  "dead_heart": {
   "english": "Dead Heart",
   "tagalog": "Deadheart",
   "cebuano": "Deadheart",
   "hiligaynon": "Deadheart"
  },
  "green_leafhopper": {
   "english": "Green Leafhopper",
   "tagalog": "Berdeng Ngusong Kabayo",
   "cebuano": "Green Leafhopper",
   "hiligaynon": "Green Leafhopper"
  },
  "leaf_folders": {
   "english": "Leaf Folders",
   "tagalog": "Maniniklop (Leaf Folder)",
   "cebuano": "Leaf Folder",
   "hiligaynon": "Leaf Folder"
  },
  "leafhopper": {
   "english": "Leafhopper",
   "tagalog": "Ngusong Kabayo (Leafhopper)",
   "cebuano": "Leafhopper",
   "hiligaynon": "Leafhopper"
  },
  "rice_bug": {
   "english": "Rice Bug",
   "tagalog": "Atangya (Rice Bug)",
   "cebuano": "Atangya (Rice Bug)",
   "hiligaynon": "Atangya (Rice Bug)"
  },
  "rice_gall_midg": {
   "english": "Rice Gall Midge",
   "tagalog": "Rice Gall Midge",
   "cebuano": "Rice Gall Midge",
   "hiligaynon": "Rice Gall Midge"
  },
  "rice_hispa": {
   "english": "Rice Hispa",
   "tagalog": "Rice Hispa",
   "cebuano": "Rice Hispa",
   "hiligaynon": "Rice Hispa"
  },
  "rice_leaf_roller": {
   "english": "Rice Leaf Roller",
   "tagalog": "Magbibilot (Leaf Roller)",
   "cebuano": "Leaf Roller",
   "hiligaynon": "Leaf Roller"
  },
  "rice_stem_borer": {
   "english": "Rice Stem Borer",
   "tagalog": "Aksip (Stem Borer)",
   "cebuano": "Stem Borer",
   "hiligaynon": "Stem Borer"
  },
  "rice_thrips": {
   "english": "Rice Thrips",
   "tagalog": "Rice Thrips",
   "cebuano": "Rice Thrips",
   "hiligaynon": "Rice Thrips"
  },
  "rice_water_weevil": {
   "english": "Rice Water Weevil",
   "tagalog": "Rice Water Weevil",
   "cebuano": "Rice Water Weevil",
   "hiligaynon": "Rice Water Weevil"
  },
  "whorl_maggot": {
   "english": "Whorl Maggot",
   "tagalog": "Langaw Palay (Whorl Maggot)",
   "cebuano": "Whorl Maggot",
   "hiligaynon": "Whorl Maggot"
  }
 },
 "severity_labels": {
  "english": {
   "HEALTHY": "HEALTHY",
   "LOW": "LOW",
   "MODERATE": "MODERATE",
   "SEVERE": "SEVERE",
   "UNKNOWN": "UNKNOWN"
  },
  "tagalog": {
   "HEALTHY": "MALUSOG",
   "LOW": "MABABA",
   "MODERATE": "KATAMTAMAN",
   "SEVERE": "MATINDI",
   "UNKNOWN": "HINDI ALAM"
  },
  "cebuano": {
   "HEALTHY": "HIMSOG",
   "LOW": "GAMAY",
   "MODERATE": "KASARANGAN",
   "SEVERE": "GRABE",
   "UNKNOWN": "DILI KAHIBALOAN"
  },
  "hiligaynon": {
   "HEALTHY": "HIMSOG",
   "LOW": "GAMAY",
   "MODERATE": "TUNGA-TUNGA",
   "SEVERE": "GRABE",
   "UNKNOWN": "INDI MAHIBALUAN"
  }
 },
 "severity": {
  "healthy_rice_plant": {
   "label": "HEALTHY",
   "percent": 0,
   "messages": {
    "english": "The plant appears to be in good condition.",
    "tagalog": "Mukhang nasa mabuting kondisyon ang halaman.",
    "cebuano": "Daw maayo ang kahimtang sa tanom.",
    "hiligaynon": "Daw maayo ang kahimtangan sang tanum."
   }
  },
  "bacterial_leaf_blight": {
   "label": "SEVERE",
   "percent": 60,
   "messages": {
    "english": "Can cause up to 60% yield loss if left untreated during the tillering stage.",
    "tagalog": "Maaaring magdulot ng hanggang 60% na pagkawala ng ani kung hindi malulunasan sa yugto ng pagsusuwi.",
    "cebuano": "Makahatag og hangtod 60% nga pagkawala sa ani kung dili matambalan sa yugto sa tillering.",
    "hiligaynon": "Mahimo magpahinabo sing tubtob 60% nga pagkawala sang ani kon indi mabulong sa yugto sang tillering."
   }
  },
  "bacterial_leaf_streak": {
   "label": "MODERATE",
   "percent": 35,
   "messages": {
    "english": "Bacterial streaking reduces leaf area for photosynthesis, typically causing moderate yield loss.",
    "tagalog": "Binabawasan ng bacterial streaking ang bahagi ng dahong gumagawa ng pagkain ng halaman, kaya karaniwang katamtaman ang pagkawala ng ani.",
    "cebuano": "Gipamenos sa bacterial streaking ang bahin sa dahon nga naghimo og pagkaon sa tanom, mao nga kasagaran kasarangan ang pagkawala sa ani.",
    "hiligaynon": "Ginapaubos sang bacterial streaking ang bahin sang dahon nga nagahimo sing pagkaon sang tanum, gani kinaandan tunga-tunga lang ang pagkawala sang ani."
   }
  },
  "brown_spot": {
   "label": "MODERATE",
   "percent": 30,
   "messages": {
    "english": "Weakens grain filling; can cause notable yield and quality loss under nutrient-poor soil.",
    "tagalog": "Pinahihina ang pagpupuno ng butil; maaaring magdulot ng kapansin-pansing pagkawala ng ani at kalidad sa lupang kulang sa sustansya.",
    "cebuano": "Gipahuyang ang pagpuno sa lugas; makahatag og dakong pagkawala sa ani ug kalidad kung kulang sa sustansya ang yuta.",
    "hiligaynon": "Ginapaluya ang pagpuno sang lugas; mahimo magpahinabo sing daku nga pagkawala sang ani kag kalidad kon kulang sa sustansya ang duta."
   }
  },
  "downy_mildew": {
   "label": "MODERATE",
   "percent": 35,
   "messages": {
    "english": "Stunts growth and distorts leaves, moderately reducing yield if it spreads early.",
    "tagalog": "Nakabansot ng paglaki at nakabaluktot ng mga dahon; katamtamang nababawasan ang ani kapag maagang kumalat.",
    "cebuano": "Gipabantut ang tubo ug gipabaluktot ang mga dahon; kasarangan ang pagkunhod sa ani kung sayo mokaylap.",
    "hiligaynon": "Ginapabansot ang tubo kag ginapabaliko ang mga dahon; tunga-tunga ang pagkubos sang ani kon aga magkalapta."
   }
  },
  "leaf_blast": {
   "label": "SEVERE",
   "percent": 80,
   "messages": {
    "english": "Highly destructive; neck blast infections can cause up to 80% yield loss.",
    "tagalog": "Lubhang mapinsala; ang neck blast ay maaaring magdulot ng hanggang 80% na pagkawala ng ani.",
    "cebuano": "Grabeng makadaot; ang neck blast makahatag og hangtod 80% nga pagkawala sa ani.",
    "hiligaynon": "Grabe kaayo ang kadaot; ang neck blast mahimo magpahinabo sing tubtob 80% nga pagkawala sang ani."
   }
  },
  "rice_false_smut": {
   "label": "MODERATE",
   "percent": 30,
   "messages": {
    "english": "Generally causes 10-30% yield loss depending on weather and severity.",
    "tagalog": "Karaniwang nagdudulot ng 10-30% na pagkawala ng ani depende sa panahon at tindi ng atake.",
    "cebuano": "Kasagarang hinungdan sa 10-30% nga pagkawala sa ani depende sa panahon ug kabug-at sa atake.",
    "hiligaynon": "Kinaandan nagapahinabo sing 10-30% nga pagkawala sang ani depende sa panahon kag kabug-at sang atake."
   }
  },
  "sheath_blight": {
   "label": "MODERATE",
   "percent": 40,
   "messages": {
    "english": "Often causes 20-50% yield loss, especially in dense, high-fertilizer canopies.",
    "tagalog": "Madalas magdulot ng 20-50% na pagkawala ng ani, lalo na sa siksik na taniman na maraming abono.",
    "cebuano": "Kasagarang hinungdan sa 20-50% nga pagkawala sa ani, labi na sa dasok nga tanom nga daghan og abono.",
    "hiligaynon": "Kinaandan nagapahinabo sing 20-50% nga pagkawala sang ani, labi na sa masikip nga tanum nga damo sang abono."
   }
  },
  "tungro_virus": {
   "label": "SEVERE",
   "percent": 85,
   "messages": {
    "english": "Can wipe out crops entirely if infection happens early in the vegetative stage.",
    "tagalog": "Maaaring maubos ang buong pananim kung maaga ang impeksyon sa yugto ng paglaki.",
    "cebuano": "Mahimong mawagtang ang tibuok tanom kung sayo ang impeksyon sa yugto sa pagtubo.",
    "hiligaynon": "Mahimo maubos ang bug-os nga tanum kon aga ang impeksyon sa yugto sang pagtubo."
   }
  },
  "applesnail_eggs": {
   "label": "SEVERE",
   "percent": 75,
   "messages": {
    "english": "Golden apple snails can completely destroy young seedlings and seedbeds quickly.",
    "tagalog": "Mabilis na masisira ng golden kuhol ang mga punla at seedbed.",
    "cebuano": "Ang golden kuhol dali kaayong makadaot sa mga punla ug seedbed.",
    "hiligaynon": "Ang golden kuhol mabilis nga makadaot sang mga punla kag seedbed."
   }
  },
  "brown_planthopper": {
   "label": "SEVERE",
   "percent": 90,
   "messages": {
    "english": "Causes severe hopperburn, leading to massive or complete yield loss.",
    "tagalog": "Nagdudulot ng matinding hopperburn na humahantong sa malaki o kumpletong pagkawala ng ani.",
    "cebuano": "Hinungdan sa grabeng hopperburn nga moresulta sa dako o hingpit nga pagkawala sa ani.",
    "hiligaynon": "Nagapahinabo sing grabe nga hopperburn nga nagaresulta sa daku ukon bug-os nga pagkawala sang ani."
   }
  },
  "dead_heart": {
   "label": "MODERATE",
   "percent": 30,
   "messages": {
    "english": "A stem borer symptom in the vegetative stage; typically causes 10-30% tiller loss.",
    "tagalog": "Sintomas ng stem borer sa yugto ng paglaki; karaniwang 10-30% ng mga suwi ang nawawala.",
    "cebuano": "Simtomas sa stem borer sa yugto sa pagtubo; kasagaran 10-30% sa mga tillers ang mawala.",
    "hiligaynon": "Simtomas sang stem borer sa yugto sang pagtubo; kinaandan 10-30% sang mga tillers ang nawawala."
   }
  },
  "green_leafhopper": {
   "label": "MODERATE",
   "percent": 30,
   "messages": {
    "english": "Direct feeding damage is moderate, but it is a key vector for tungro virus.",
    "tagalog": "Katamtaman ang direktang pinsala, ngunit ito ang pangunahing tagapagdala ng tungro virus.",
    "cebuano": "Kasarangan ang direktang kadaot, apan importante kining tigdala sa tungro virus.",
    "hiligaynon": "Tunga-tunga ang direkta nga kadaot, pero importante ini nga manugdala sang tungro virus."
   }
  },
  "leaf_folders": {
   "label": "LOW",
   "percent": 20,
   "messages": {
    "english": "Damage looks severe but usually only results in minor yield loss (up to 20%).",
    "tagalog": "Malala ang itsura ng pinsala ngunit karaniwang maliit lamang ang nawawalang ani (hanggang 20%).",
    "cebuano": "Daw grabe ang hitsura sa kadaot apan kasagaran gamay ra ang pagkawala sa ani (hangtod 20%).",
    "hiligaynon": "Daw grabe ang hitsura sang kadaot pero kinaandan gamay lang ang pagkawala sang ani (tubtob 20%)."
   }
  },
  "leafhopper": {
   "label": "MODERATE",
   "percent": 30,
   "messages": {
    "english": "Direct damage is moderate, but they are dangerous vectors for viral diseases.",
    "tagalog": "Katamtaman ang direktang pinsala, ngunit mapanganib silang tagapagdala ng mga sakit na virus.",
    "cebuano": "Kasarangan ang direktang kadaot, apan peligro kining tigdala sa mga sakit nga virus.",
    "hiligaynon": "Tunga-tunga ang direkta nga kadaot, pero delikado ini nga manugdala sang mga sakit nga virus."
   }
  },
  "rice_bug": {
   "label": "SEVERE",
   "percent": 80,
   "messages": {
    "english": "Sucks sap from developing grains, capable of causing up to 80% empty grains.",
    "tagalog": "Sinisipsip ang katas ng butil na malagatas pa, kaya hanggang 80% ay maaaring maging pipis.",
    "cebuano": "Gisuyop ang katas sa mga lugas nga gatas pa, mao nga hangtod 80% mahimong walay sulod.",
    "hiligaynon": "Ginasupsop ang katas sang mga lugas nga malagatas pa, gani tubtob 80% mahimo mangin wala sing sulod."
   }
  },
  "rice_gall_midg": {
   "label": "MODERATE",
   "percent": 40,
   "messages": {
    "english": "Damages tillers (onion shoots), causing moderate yield reduction.",
    "tagalog": "Sinisira ang mga suwi (onion shoot), na nagdudulot ng katamtamang pagbaba ng ani.",
    "cebuano": "Gidaot ang mga tillers (onion shoot), hinungdan sa kasarangang pagkunhod sa ani.",
    "hiligaynon": "Ginadaot ang mga tillers (onion shoot), nga nagapahinabo sing tunga-tunga nga pagkubos sang ani."
   }
  },
  "rice_hispa": {
   "label": "MODERATE",
   "percent": 35,
   "messages": {
    "english": "Larvae mine leaf tissue and adults scrape leaf surfaces, moderately reducing yield.",
    "tagalog": "Kinakain ng uod ang loob ng dahon at kinakayod ng adult ang ibabaw nito, kaya katamtamang bumababa ang ani.",
    "cebuano": "Gikaon sa larva ang sulod sa dahon ug gikuskos sa hamtong ang ibabaw niini, mao nga kasarangan ang pagkunhod sa ani.",
    "hiligaynon": "Ginakaon sang larva ang sulod sang dahon kag ginakaskas sang hamtong ang ibabaw sini, gani tunga-tunga ang pagkubos sang ani."
   }
  },
  "rice_leaf_roller": {
   "label": "LOW",
   "percent": 20,
   "messages": {
    "english": "Similar to leaf folders; rarely causes total crop failure.",
    "tagalog": "Katulad ng leaf folder; bihirang humantong sa lubos na pagkasira ng pananim.",
    "cebuano": "Sama sa leaf folder; talagsa ra nga mosangpot sa hingpit nga pagkapakyas sa tanom.",
    "hiligaynon": "Pareho sa leaf folder; wala gid nagapahinabo sing bug-os nga pagkalaglag sang tanum."
   }
  },
  "rice_stem_borer": {
   "label": "MODERATE",
   "percent": 30,
   "messages": {
    "english": "Causes deadhearts and whiteheads; typically results in 10-30% yield loss.",
    "tagalog": "Nagdudulot ng deadheart at whitehead; karaniwang 10-30% ang nawawalang ani.",
    "cebuano": "Hinungdan sa deadheart ug whitehead; kasagaran 10-30% ang mawala nga ani.",
    "hiligaynon": "Nagapahinabo sing deadheart kag whitehead; kinaandan 10-30% ang nawawala nga ani."
   }
  },
  "rice_thrips": {
   "label": "LOW",
   "percent": 15,
   "messages": {
    "english": "Causes leaf curling and silvering in seedlings; usually only minor yield impact.",
    "tagalog": "Nagdudulot ng pagkukulot at pagpuputi ng dahon ng punla; karaniwang maliit lamang ang epekto sa ani.",
    "cebuano": "Hinungdan sa pagkulot ug pagputi sa dahon sa mga punla; kasagaran gamay ra ang epekto sa ani.",
    "hiligaynon": "Nagapahinabo sing pagkulot kag pagpaputi sang dahon sang mga punla; kinaandan gamay lang ang epekto sa ani."
   }
  },
  "rice_water_weevil": {
   "label": "MODERATE",
   "percent": 35,
   "messages": {
    "english": "Larvae prune roots, moderately reducing tillering and yield.",
    "tagalog": "Pinuputol ng uod ang mga ugat, kaya katamtamang bumababa ang pagsusuwi at ani.",
    "cebuano": "Gikutlo sa larva ang mga gamot, mao nga kasarangan ang pagkunhod sa tillering ug sa ani.",
    "hiligaynon": "Ginaputol sang larva ang mga gamot, gani tunga-tunga ang pagkubos sang tillering kag ani."
   }
  },
  "whorl_maggot": {
   "label": "LOW",
   "percent": 20,
   "messages": {
    "english": "Scars young leaves as they unfurl; usually only minor yield impact.",
    "tagalog": "Nag-iiwan ng peklat sa batang dahon habang bumubuka; karaniwang maliit lamang ang epekto sa ani.",
    "cebuano": "Gilamasan ang mga bata nga dahon samtang nagbukhad; kasagaran gamay ra ang epekto sa ani.",
    "hiligaynon": "Ginapaklatan ang mga bata nga dahon samtang nagabukad; kinaandan gamay lang ang epekto sa ani."
   }
  }
 },
 "ui": {
  "english": {
   "loading": "Generating information with Groq AI…",
   "source_groq": "Groq AI",
   "source_fallback": "Standard knowledge base"
  },
  "tagalog": {
   "loading": "Gumagawa ng impormasyon gamit ang Groq AI…",
   "source_groq": "Groq AI",
   "source_fallback": "Karaniwang kaalaman"
  },
  "cebuano": {
   "loading": "Naghimo og impormasyon gamit ang Groq AI…",
   "source_groq": "Groq AI",
   "source_fallback": "Sukaranang kahibalo"
  },
  "hiligaynon": {
   "loading": "Nagahimo sang impormasyon gamit ang Groq AI…",
   "source_groq": "Groq AI",
   "source_fallback": "Sukaranon nga kaaram"
  }
 }
}
JSON;
}