import React, { useEffect, useState } from 'react';
import { View, Text, FlatList, StyleSheet, TouchableOpacity, RefreshControl } from 'react-native';
import api from '../services/api';
import { Conversation } from '../types';

export default function ConversationsScreen() {
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const load = async () => {
    try {
      const { data } = await api.get('/conversations');
      setConversations(data.data || data);
    } catch {}
  };

  useEffect(() => { load(); }, []);

  const onRefresh = async () => { setRefreshing(true); await load(); setRefreshing(false); };

  const formatDate = (d: string) => {
    const date = new Date(d);
    const now = new Date();
    if (date.toDateString() === now.toDateString()) return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Conversas</Text>
      {conversations.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Nenhuma conversa ainda</Text>
        </View>
      ) : (
        <FlatList
          data={conversations}
          keyExtractor={(item) => String(item.id)}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor="#6366f1" />}
          renderItem={({ item }) => (
            <TouchableOpacity style={styles.item}>
              <View style={styles.avatar}>
                <Text style={styles.avatarText}>{item.contact_name?.[0]?.toUpperCase() || '?'}</Text>
              </View>
              <View style={styles.content}>
                <View style={styles.row}>
                  <Text style={styles.name} numberOfLines={1}>{item.contact_name}</Text>
                  <Text style={styles.time}>{formatDate(item.last_message_at)}</Text>
                </View>
                <View style={styles.row}>
                  <Text style={styles.phone}>{item.contact_phone}</Text>
                  <View style={styles.badges}>
                    <Text style={styles.badge}>{item.messages_count} msgs</Text>
                    {item.actions_count > 0 && <Text style={[styles.badge, styles.actionBadge]}>{item.actions_count} ações</Text>}
                  </View>
                </View>
              </View>
            </TouchableOpacity>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0f172a', padding: 16 },
  title: { fontSize: 24, fontWeight: '800', color: '#fff', marginTop: 16, marginBottom: 16 },
  empty: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  emptyText: { color: '#64748b', fontSize: 16 },
  item: { flexDirection: 'row', backgroundColor: '#1e293b', borderRadius: 12, padding: 14, marginBottom: 8, gap: 12, alignItems: 'center' },
  avatar: { width: 44, height: 44, borderRadius: 22, backgroundColor: '#334155', justifyContent: 'center', alignItems: 'center' },
  avatarText: { color: '#fff', fontSize: 18, fontWeight: '700' },
  content: { flex: 1 },
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  name: { color: '#fff', fontSize: 15, fontWeight: '700', flex: 1 },
  time: { color: '#64748b', fontSize: 11 },
  phone: { color: '#64748b', fontSize: 12, marginTop: 4 },
  badges: { flexDirection: 'row', gap: 6, marginTop: 4 },
  badge: { color: '#94a3b8', fontSize: 10, backgroundColor: '#0f172a', paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4 },
  actionBadge: { color: '#22c55e', backgroundColor: '#052e16' },
});
