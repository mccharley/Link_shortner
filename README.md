# Link_shortner
 a simple URL shortener to get you going.

 Test your short link using the URL string:
 http://localhost/link_shortner/index.php?shortlink

Would like to see how collaborators can improve the app.
- The entropy of the short link can be improved on.
- Given the current entropy of 6, and a character range of a-z (26 lower case), A-Z (26 upper case) and 0-1 (10 numbers), we have C(62,6) non-unique combinations i.e. C(n,r) of characters.
  That's 61,474,519 possible short links we can host.  
- Increasing this entropy by even a factor of 1 or 2 (i.e. from 6 to 7 or 8) adds another 491,796,152 or 3,381,098,545 to this possibility with the largest value possibility of 62 characters.
  Essentially, the higher the entropy, the higher the number of possible combinations and corresponding number of short links we can create.
  however note that the number of possibilities is maximum at 31 (half the data set) and begins to reduce after this entropy value:
  
  Possibilities        entropy
  
  450883717216034179     30
  
  465428353255261088     31
  
  450883717216034179     32
  
  409894288378212890     33
  

- Application can be improved to automatically increase entropy based on the current entropy utilization and count of short links related to URL's domain or some other metric.

- you can increase the entropy by increasing the value of passed to shortner_ID
  $dataset[1] = $modify_URL -> shortfier_ID(6,$mysqli); // short link length

-short link URL can be improved to not require the "/index.php?". I hope someone can help find a way to achieve this and improve on the cleanness of the final short link.
