
var builder = WebApplication.CreateBuilder(args);
var app = builder.Build();

// Seznam dostupných atrakcí
var attractions = new[]
{
    new
    {
        id = "ATTR-PRG-01",
        name = "Karlův most",
        location = "Praha",
        type = "outdoor",
        pricing = new { price_czk = 0, price_eur = 0.0, is_free = true }
    },
    new
    {
        id = "ATTR-PRG-02",
        name = "Národní muzeum",
        location = "Praha",
        type = "indoor",
        pricing = new { price_czk = 350, price_eur = 14.0, is_free = false }
    },
    new
    {
        id = "ATTR-PRG-03",
        name = "Pražský hrad (Okruh)",
        location = "Praha",
        type = "outdoor",
        pricing = new { price_czk = 450, price_eur = 18.0, is_free = false }
    },
    new
    {
        id = "ATTR-BRN-01",
        name = "Hrad Špilberk",
        location = "Brno",
        type = "outdoor",
        pricing = new { price_czk = 200, price_eur = 8.0, is_free = false }
    },
    new
    {
        id = "ATTR-BRN-02",
        name = "Vila Tugendhat",
        location = "Brno",
        type = "indoor",
        pricing = new { price_czk = 400, price_eur = 16.0, is_free = false }
    },
    new
    {
        id = "ATTR-KRU-01",
        name = "Státní hrad a zámek Český Krumlov",
        location = "Český Krumlov",
        type = "outdoor",
        pricing = new { price_czk = 300, price_eur = 12.0, is_free = false }
    }
};

// GET / - Popis služby
app.MapGet("/", () => Results.Ok(new
{
    service = "Lokální atrakce API",
    version = "1.0",
    description = "API pro vyhledávání turistických atrakcí",
    endpoints = new[]
    {
        "GET /",
        "GET /attractions/date/?city=Praha"
    }
}));

// GET /attractions/date/?city=Praha
app.MapGet("/attractions/date", (string? city) =>
{
    if (string.IsNullOrWhiteSpace(city))
    {
        return Results.BadRequest(new
        {
            error = "Musíte zadat město."
        });
    }

    var result = attractions
        .Where(a => a.location.Equals(
            city.Trim(),
            StringComparison.OrdinalIgnoreCase))
        .ToArray();

    return Results.Ok(new
    {
        city = city.Trim(),
        count = result.Length,
        attractions = result
    });
});

app.Run();
