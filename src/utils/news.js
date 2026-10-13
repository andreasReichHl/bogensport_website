import { useEffect, useState } from "react";

export function useNewsFeed() {
  const [posts, setPosts] = useState([]);
  const [isLoaded, setIsLoaded] = useState(false);

  useEffect(() => {
    let isMounted = true;

    fetch("/news-feed.php", { cache: "no-store" })
      .then((response) => (response.ok ? response.json() : { posts: [] }))
      .then((data) => {
        if (!isMounted) {
          return;
        }

        setPosts(Array.isArray(data.posts) ? data.posts : []);
        setIsLoaded(true);
      })
      .catch(() => {
        if (isMounted) {
          setPosts([]);
          setIsLoaded(true);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  return { posts, isLoaded };
}

export function parseDate(value) {
  if (!value) {
    return null;
  }

  const date = new Date(value.replace(" ", "T"));

  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatDate(value) {
  const date = parseDate(value);

  if (!date) {
    return value || "";
  }

  return new Intl.DateTimeFormat("de-DE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
  }).format(date);
}

export function textPreview(html, maxLength) {
  const textarea = document.createElement("textarea");
  textarea.innerHTML = html.replace(/<[^>]*>/g, " ");

  const text = textarea.value
    .replace(/\u00a0/g, " ")
    .replace(/\s+/g, " ")
    .trim();

  if (!maxLength || text.length <= maxLength) {
    return text;
  }

  return `${text.slice(0, maxLength - 3)}...`;
}
