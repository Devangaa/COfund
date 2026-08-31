import api from './api'

export const campaignImageService = {
  store: (slug, file) => {
    const formData = new FormData()
    formData.append('image', file)
    return api.post(`/campaigns/${slug}/images`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
  destroyMany: (slug, imageIds) =>
    api.delete(`/campaigns/${slug}/images`, { data: { ids: imageIds } }),
}
