/* ----------------------------------------------------------------------------
 * Easy!Appointments - Online Appointment Scheduler
 *
 * @package     EasyAppointments
 * @author      A.Tselegidis <alextselegidis@gmail.com>
 * @copyright   Copyright (c) Alex Tselegidis
 * @license     https://opensource.org/licenses/GPL-3.0 - GPLv3
 * @link        https://easyappointments.org
 * ---------------------------------------------------------------------------- */

/**
 * Romanian administrative units reference dataset (judete + municipii).
 *
 * Static reference data used to provide autocomplete SUGGESTIONS on the
 * county (judet) and city (oras) text inputs across the app. Suggestions
 * only - all fields remain free-text, any typed value is saved as-is.
 *
 * Sources (canonical, SIRUTA-based):
 *  - Counties + municipalities list: ro.wikipedia.org "Municipiile Romaniei"
 *    (mirrors the official SIRUTA register / Legea 351/2001, 103 municipii)
 *  - City-to-county mapping: en.wikipedia.org "List of cities and towns in Romania"
 *
 * Municipiul Bucuresti is a special county-level unit whose "cities" are its
 * 6 administrative sectors, formatted "Sector N Bucuresti".
 */
window.App = window.App || {};
App.Data = App.Data || {};

App.Data.RoLocations = {
    "counties": [
        "Alba",
        "Arad",
        "Argeș",
        "Bacău",
        "Bihor",
        "Bistrița-Năsăud",
        "Botoșani",
        "Brașov",
        "Brăila",
        "București",
        "Buzău",
        "Caraș-Severin",
        "Cluj",
        "Constanța",
        "Covasna",
        "Călărași",
        "Dolj",
        "Dâmbovița",
        "Galați",
        "Giurgiu",
        "Gorj",
        "Harghita",
        "Hunedoara",
        "Ialomița",
        "Iași",
        "Ilfov",
        "Maramureș",
        "Mehedinți",
        "Mureș",
        "Neamț",
        "Olt",
        "Prahova",
        "Satu Mare",
        "Sibiu",
        "Suceava",
        "Sălaj",
        "Teleorman",
        "Timiș",
        "Tulcea",
        "Vaslui",
        "Vrancea",
        "Vâlcea"
    ],
    "municipalities": {
        "Alba": [
            "Aiud",
            "Alba Iulia",
            "Blaj",
            "Sebeș"
        ],
        "Arad": [
            "Arad"
        ],
        "Argeș": [
            "Curtea de Argeș",
            "Câmpulung",
            "Pitești"
        ],
        "Bacău": [
            "Bacău",
            "Moinești",
            "Onești"
        ],
        "Bihor": [
            "Beiuș",
            "Marghita",
            "Oradea",
            "Salonta"
        ],
        "Bistrița-Năsăud": [
            "Bistrița"
        ],
        "Botoșani": [
            "Botoșani",
            "Dorohoi"
        ],
        "Brașov": [
            "Brașov",
            "Codlea",
            "Făgăraș",
            "Săcele"
        ],
        "Brăila": [
            "Brăila"
        ],
        "București": [
            "Sector 1 București",
            "Sector 2 București",
            "Sector 3 București",
            "Sector 4 București",
            "Sector 5 București",
            "Sector 6 București"
        ],
        "Buzău": [
            "Buzău",
            "Râmnicu Sărat"
        ],
        "Caraș-Severin": [
            "Caransebeș",
            "Reșița"
        ],
        "Cluj": [
            "Cluj-Napoca",
            "Câmpia Turzii",
            "Dej",
            "Gherla",
            "Turda"
        ],
        "Constanța": [
            "Constanța",
            "Mangalia",
            "Medgidia"
        ],
        "Covasna": [
            "Sfântu Gheorghe",
            "Târgu Secuiesc"
        ],
        "Călărași": [
            "Călărași",
            "Oltenița"
        ],
        "Dolj": [
            "Băilești",
            "Calafat",
            "Craiova"
        ],
        "Dâmbovița": [
            "Moreni",
            "Târgoviște"
        ],
        "Galați": [
            "Galați",
            "Tecuci"
        ],
        "Giurgiu": [
            "Giurgiu"
        ],
        "Gorj": [
            "Motru",
            "Târgu Jiu"
        ],
        "Harghita": [
            "Gheorgheni",
            "Miercurea Ciuc",
            "Odorheiu Secuiesc",
            "Toplița"
        ],
        "Hunedoara": [
            "Brad",
            "Deva",
            "Hunedoara",
            "Lupeni",
            "Orăștie",
            "Petroșani",
            "Vulcan"
        ],
        "Ialomița": [
            "Fetești",
            "Slobozia",
            "Urziceni"
        ],
        "Iași": [
            "Iași",
            "Pașcani"
        ],
        "Ilfov": [],
        "Maramureș": [
            "Baia Mare",
            "Sighetu Marmației"
        ],
        "Mehedinți": [
            "Drobeta-Turnu Severin",
            "Orșova"
        ],
        "Mureș": [
            "Reghin",
            "Sighișoara",
            "Târgu Mureș",
            "Târnăveni"
        ],
        "Neamț": [
            "Piatra Neamț",
            "Roman"
        ],
        "Olt": [
            "Caracal",
            "Slatina"
        ],
        "Prahova": [
            "Câmpina",
            "Ploiești"
        ],
        "Satu Mare": [
            "Carei",
            "Satu Mare"
        ],
        "Sibiu": [
            "Mediaș",
            "Sibiu"
        ],
        "Suceava": [
            "Câmpulung Moldovenesc",
            "Fălticeni",
            "Rădăuți",
            "Suceava",
            "Vatra Dornei"
        ],
        "Sălaj": [
            "Zalău"
        ],
        "Teleorman": [
            "Alexandria",
            "Roșiori de Vede",
            "Turnu Măgurele"
        ],
        "Timiș": [
            "Lugoj",
            "Timișoara"
        ],
        "Tulcea": [
            "Tulcea"
        ],
        "Vaslui": [
            "Bârlad",
            "Huși",
            "Vaslui"
        ],
        "Vrancea": [
            "Adjud",
            "Focșani"
        ],
        "Vâlcea": [
            "Drăgășani",
            "Râmnicu Vâlcea"
        ]
    }
};
