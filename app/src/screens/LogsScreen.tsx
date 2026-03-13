import React from 'react';
import { View, Text, FlatList, StyleSheet, TouchableOpacity } from 'react-native';
import { useService } from '../store/service';

const typeColors: Record<string, string> = {
  message_received: '#6366f1',
  ai_response: '#22c55e',
  action_executed: '#f59e0b',
  error: '#ef4444',
};

const typeLabels: Record<string, string> = {
  message_received: 'MSG',
  ai_response: 'IA',
  action_executed: 'AÇÃO',
  error: 'ERRO',
};

export default function LogsScreen() {
  const { logs, clearLogs } = useService();

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Logs em Tempo Real</Text>
        <TouchableOpacity onPress={clearLogs}>
          <Text style={styles.clearBtn}>Limpar</Text>
        </TouchableOpacity>
      </View>
      {logs.length === 0 ? (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Nenhum log ainda</Text>
          <Text style={styles.emptyDesc}>Os logs aparecerão aqui quando o serviço processar mensagens</Text>
        </View>
      ) : (
        <FlatList
          data={logs}
          keyExtractor={(item) => item.id}
          renderItem={({ item }) => (
            <View style={styles.logItem}>
              <View style={[styles.badge, { backgroundColor: typeColors[item.type] || '#64748b' }]}>
                <Text style={styles.badgeText}>{typeLabels[item.type] || item.type}</Text>
              </View>
              <View style={styles.logContent}>
                {item.contactName && <Text style={styles.logContact}>{item.contactName}</Text>}
                <Text style={styles.logMessage} numberOfLines={2}>
                  {item.message || item.response || item.error || item.actionType}
                </Text>
                {(item.correlationId || item.batchId || item.batchSize) && (
                  <Text style={styles.logMeta} numberOfLines={2}>
                    {item.correlationId ? `Corr: ${item.correlationId}` : ''}
                    {item.batchId ? `${item.correlationId ? ' · ' : ''}Lote: ${item.batchId}` : ''}
                    {typeof item.batchSize === 'number' ? `${item.correlationId || item.batchId ? ' · ' : ''}Msgs: ${item.batchSize}` : ''}
                  </Text>
                )}
                <Text style={styles.logTime}>{new Date(item.timestamp).toLocaleTimeString('pt-BR')}</Text>
              </View>
            </View>
          )}
        />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#0f172a', padding: 16 },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16, marginTop: 16 },
  title: { fontSize: 20, fontWeight: '800', color: '#fff' },
  clearBtn: { color: '#6366f1', fontSize: 14, fontWeight: '600' },
  empty: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  emptyText: { color: '#64748b', fontSize: 16, fontWeight: '600' },
  emptyDesc: { color: '#475569', fontSize: 13, marginTop: 8, textAlign: 'center' },
  logItem: { flexDirection: 'row', backgroundColor: '#1e293b', borderRadius: 12, padding: 12, marginBottom: 8, gap: 12, alignItems: 'flex-start' },
  badge: { borderRadius: 6, paddingHorizontal: 8, paddingVertical: 4 },
  badgeText: { color: '#fff', fontSize: 10, fontWeight: '800' },
  logContent: { flex: 1 },
  logContact: { color: '#fff', fontSize: 13, fontWeight: '700' },
  logMessage: { color: '#94a3b8', fontSize: 12, marginTop: 2 },
  logMeta: { color: '#60a5fa', fontSize: 11, marginTop: 4 },
  logTime: { color: '#475569', fontSize: 10, marginTop: 4 },
});
