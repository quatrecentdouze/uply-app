import { useEffect } from 'react';

export function useDocumentTitle(title: string | null): void {
  useEffect(() => {
    if (title !== null) {
      document.title = title;
    }
  }, [title]);
}
