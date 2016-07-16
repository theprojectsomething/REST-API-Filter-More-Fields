# REST API - Filter (More) Fields
Filter (more) fields returned by the WP Rest API with deep filters, using syntax similar to [Facebook's Graph API](http://bit.ly/2a6cULe).


### Steps to success:

1. This is a Wordpress plugin, so you'll need that
2. Install the [WP Rest API](http://v2.wp-api.org/)
3. If you're using ACF, install the ACF to WP API plugin ([here](https://wordpress.org/plugins/acf-to-wp-api/)), there are also a bunch of other great plugins out there to extend the API and help reduce the number of calls made
4. Install Filter (More) Fields
5. Try a query: "{https://your-groovy-site}/wp-json/wp/v2/posts?fields=id,title" 

## Filtering Syntax
The plugin allows filtering multiple levels of returnable JSON fields with some simple yet nifty syntax lifted from Facebook.

### *{api-endpoint}/posts?fields=id,title*
Returns only the 'id' and 'title' for each of the posts
```
[
  {
    "id": 1,
    "title": "top post bro!"
  }
]
```

### *{api-endpoint}/posts?fields=id,title,acf{related{id,excerpt}}*
Returns an 'id' and 'title', along with the 'id' and 'excerpt' fields from an ACF custom field called 'related' (let's say it's a list of pages)
```
[
  {
    "id": 1,
    "title": "top post bro!",
    "acf": {
      "related": [
        {
          "id": 2,
          "excerpt": "next level!!"
        },
        {
          "id": 3,
          "excerpt": "way down!"
        }
      ]
    }
  }
]
```

### *{api-endpoint}/posts?fields=id,title,acf{related.limit(1){id,excerpt,acf{categories{title}}}}*
Same as above, except now we only want one of the related pages and how 'bout some more detail
```
[
  {
    "id": 1,
    "title": "top post bro!",
    "acf": {
      "related": [
        {
          "id": 2,
          "excerpt": "next level!!",
          "acf": {
            "categories": [
              {
                "title": "no, no limits"
              },
              {
                "title": "reach for da sky"
              }
            ]
          }
        }
      ]
    }
  }
]
```


This plugin was worked up from another handy little fellow called "[REST API - Filter Fields](https://wordpress.org/plugins/rest-api-filter-fields/)" by Stephan van Rooij. Check it out!
