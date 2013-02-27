function showRandomQuoteStart( delay, autoStart )
{
	delay = delay || 10000;
	autoStart = autoStart || true;
	
	showRandomQuoteInterval = setInterval( showRandomQuote, delay );
	
	if( autoStart ) showRandomQuote();
}

function showRandomQuoteStop()
{
	clearInterval( showRandomQuoteInterval );
}

function showRandomQuote()
{
	var id = Math.floor(Math.random() * quotes.length);
	if( id == quoteId ) return showRandomQuote();
	
	quoteId = id;
	
	var quote = quotes[id];
	
	$('#quote').fadeOut
	(
		'slow',
		function()
		{
			$(this).find(".author").html( quote.author );
			$(this).find(".quote").html( quote.quote );
			$(this).fadeIn();
		}
	);
}

var quotes = 
[
	{quote:"\"Art is a marriage of the conscious and the unconscious\"",author:"Jean Cocteau"},
	{quote:"\"To sleep, perchance to dream - ay, there's the rub, For in this sleep of death what dreams may come.\"",author:"William Shakespeare"},
	{quote:"\"In my dream, I see these fantastic paintings that were done by somebody else. And I wish that I had painted them. And I wake up, and after a while the impression wears off. I say, wait a minute, those are my paintings. I dreamt them; they're mine. Then I can't remember what they were.\"",author: "David Lynch"},
	{quote:"\"I'll let you be in my dreams if I can be in yours\"",author:"Bob Dylan"},
	{quote:"\"I have dreamed a dream, but now that dream has gone from me\"",author:"King Nebuchadnezzar"},
	{quote:"\"Reality is wrong. Dreams are for real.\"",author:"Tupac Shakur"},
	{quote:"\"All human beings are also dream beings. Dreaming ties all mankind together.\"",author:"Jack Kerouac"},
	{quote:"\"Dreams are necessary to life.\"",author:"Anais Nin"},
	{quote:"\"All the things one has forgotten scream for help in dreams.\"",author:"Elias Canetti"},
	{quote:"\"He was a dreamer, a thinker, a speculative philosopher...or, as his wife would have it, an idiot.\"",author:"Douglas Adams"},
	{quote:"\"Myths are public dreams, dreams are private myths.\"",author:"Joseph Campbell"},
	{quote:"\"I don't use drugs, my dreams are frightening enough.\"",author:"M. C. Escher"},
	{quote:"\"Was it only by dreaming or writing that I could find out what I thought?\"",author:"Joan Didion"},
	{quote:"\"Dreams are often most profound when they seem the most crazy.\"",author:"Sigmund Freud"},
	{quote:"\"The internet let's you dream while you're awake.\"",author:"Douglas Coupland"}
];
var quoteId;
var showRandomQuoteInterval;